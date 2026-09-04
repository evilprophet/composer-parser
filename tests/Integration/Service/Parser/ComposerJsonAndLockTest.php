<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Service\Parser;

use EvilStudio\ComposerParser\Model\PackageConfig;
use EvilStudio\ComposerParser\Model\RepositoryList;
use EvilStudio\ComposerParser\Service\Parser\ComposerJsonAndLock;
use EvilStudio\ComposerParser\Service\Parser\RepositoryDataFactory;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;
use EvilStudio\ComposerParser\Service\Report\ReportValidator;
use EvilStudio\ComposerParser\Tests\Integration\Support\InMemoryProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ComposerJsonAndLockTest extends TestCase
{
    public function testExecuteAddsInstalledVersionCommentFromComposerLock(): void
    {
        $packageConfig = new PackageConfig([
            'includeInstalledVersion' => true,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                ['name' => 'Core', 'parserPriority' => 10, 'writerOrder' => 10, 'groupType' => 'require', 'regex' => '/^vendor\/a$/'],
                ['name' => 'Framework', 'parserPriority' => 9, 'writerOrder' => 11, 'groupType' => 'require', 'regex' => '/^vendor\/framework$/'],
                ['name' => 'Other', 'parserPriority' => 1, 'writerOrder' => 20, 'groupType' => 'require', 'regex' => '/.*/'],
                ['name' => 'Require Dev', 'parserPriority' => 1, 'writerOrder' => 30, 'groupType' => 'require-dev', 'regex' => '/.*/'],
            ],
            'observedPackages' => [],
        ]);

        $repositoryList = new RepositoryList([
            [
                'name' => 'project-a',
                'directory' => 'var/repositories/project-a',
                'remote' => 'git@gitlab.example.com:team/project-a.git',
                'branch' => 'main',
            ],
        ]);

        $provider = new InMemoryProvider([
            'project-a' => [
                'composerJson' => [
                    'require' => [
                        'vendor/a' => '^1.0',
                        'vendor/framework' => '^9.2',
                        'vendor/cache' => '^3.1',
                        'vendor/http' => '^7.8',
                        'vendor/log' => '^2.0',
                    ],
                    'require-dev' => [
                        'vendor/dev-tool' => '^3.0',
                    ],
                ],
                'composerLock' => [
                    'packages' => [
                        ['name' => 'vendor/a', 'version' => '1.2.3'],
                        ['name' => 'vendor/framework', 'version' => '9.2.1'],
                        ['name' => 'vendor/cache', 'version' => '3.1.4'],
                        ['name' => 'vendor/http', 'version' => '7.8.2'],
                        ['name' => 'vendor/log', 'version' => '2.0.0'],
                    ],
                    'packages-dev' => [
                        ['name' => 'vendor/dev-tool', 'version' => '3.4.5'],
                    ],
                ],
            ],
        ]);

        $providerManager = new ProviderManager('test', new ServiceLocator([
            'test' => static fn () => $provider,
        ]));
        $parser = new ComposerJsonAndLock($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

        $parsedData = $parser->execute()->getGroups();
        self::assertStringContainsString('Installed version: 1.2.3', $parsedData['Core']['vendor/a']['project-a']['comment']);
        self::assertStringContainsString('Installed version: 9.2.1', $parsedData['Framework']['vendor/framework']['project-a']['comment']);
        self::assertStringContainsString('Installed version: 3.1.4', $parsedData['Other']['vendor/cache']['project-a']['comment']);
        self::assertStringContainsString('Installed version: 7.8.2', $parsedData['Other']['vendor/http']['project-a']['comment']);
        self::assertStringContainsString('Installed version: 2.0.0', $parsedData['Other']['vendor/log']['project-a']['comment']);
        self::assertStringContainsString('Installed version: 3.4.5', $parsedData['Require Dev']['vendor/dev-tool']['project-a']['comment']);
    }

    public function testExecuteReadsLocksAfterBuildingPackageMatrixWithoutReloadingRepositories(): void
    {
        $packageConfig = new PackageConfig([
            'includeInstalledVersion' => true,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                ['name' => 'Packages', 'parserPriority' => 0, 'writerOrder' => 0, 'groupType' => 'require', 'regex' => '/.*/'],
            ],
            'observedPackages' => [],
        ]);
        $repositoryList = new RepositoryList([
            [
                'name' => 'project-a',
                'directory' => 'var/repositories/project-a',
                'remote' => 'git@gitlab.example.com:team/project-a.git',
                'branch' => 'main',
            ],
        ]);
        $provider = new InMemoryProvider([
            'project-a' => [
                'composerJson' => [
                    'require' => ['vendor/package' => '^1.0'],
                ],
                'composerLock' => [
                    'packages' => [
                        [
                            'name' => 'vendor/package',
                            'version' => '1.2.3',
                            'description' => 'This metadata must not remain in the second-phase cache.',
                            'extra' => ['metadata' => 'unused'],
                        ],
                    ],
                    'packages-dev' => [
                        [
                            'name' => 'vendor/dev-package',
                            'version' => '4.5.6',
                            'source' => ['url' => 'https://example.com/vendor/dev-package.git'],
                        ],
                    ],
                ],
            ],
        ]);
        $providerManager = new ProviderManager('test', new ServiceLocator([
            'test' => static fn () => $provider,
        ]));
        $parser = new ComposerJsonAndLock($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

        $parser->execute();

        self::assertSame(['project-a'], $provider->getLoadedProjectNames());
        self::assertSame(['project-a'], $provider->getComposerLockReadProjectNames());
    }

    public function testExecuteAddsInstalledVersionForTransitivePackageIndependentlyOfRepositoryOrder(): void
    {
        $projectA = [
            'name' => 'project-a',
            'directory' => 'var/repositories/project-a',
            'remote' => 'git@gitlab.example.com:team/project-a.git',
            'branch' => 'main',
        ];
        $projectB = [
            'name' => 'project-b',
            'directory' => 'var/repositories/project-b',
            'remote' => 'git@gitlab.example.com:team/project-b.git',
            'branch' => 'main',
        ];

        $groupsWhenProjectAIsFirst = $this->parseTransitivePackageGroups([$projectA, $projectB]);
        $groupsWhenProjectBIsFirst = $this->parseTransitivePackageGroups([$projectB, $projectA]);

        self::assertSame($groupsWhenProjectAIsFirst, $groupsWhenProjectBIsFirst);
        self::assertSame(
            "Installed version: 1.2.3\n",
            $groupsWhenProjectAIsFirst['Packages']['vendor/transitive']['project-a']['comment']
        );
        self::assertSame(
            "Installed version: 1.2.3\n",
            $groupsWhenProjectAIsFirst['Packages']['vendor/transitive']['project-b']['comment']
        );
    }

    public function testExecuteSkipsInstalledVersionWhenPackagesKeyMissing(): void
    {
        $packageConfig = new PackageConfig([
            'includeInstalledVersion' => true,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                ['name' => 'Core', 'parserPriority' => 10, 'writerOrder' => 10, 'groupType' => 'require', 'regex' => '/^vendor\/a$/'],
                ['name' => 'Framework', 'parserPriority' => 9, 'writerOrder' => 11, 'groupType' => 'require', 'regex' => '/^vendor\/framework$/'],
                ['name' => 'Other', 'parserPriority' => 1, 'writerOrder' => 20, 'groupType' => 'require', 'regex' => '/.*/'],
                ['name' => 'Require Dev', 'parserPriority' => 1, 'writerOrder' => 30, 'groupType' => 'require-dev', 'regex' => '/.*/'],
            ],
            'observedPackages' => [],
        ]);

        $repositoryList = new RepositoryList([
            [
                'name' => 'project-a',
                'directory' => 'var/repositories/project-a',
                'remote' => 'git@gitlab.example.com:team/project-a.git',
                'branch' => 'main',
            ],
        ]);

        $provider = new InMemoryProvider([
            'project-a' => [
                'composerJson' => [
                    'require' => [
                        'vendor/a' => '^1.0',
                        'vendor/framework' => '^9.2',
                        'vendor/cache' => '^3.1',
                        'vendor/http' => '^7.8',
                        'vendor/log' => '^2.0',
                    ],
                    'require-dev' => [
                        'vendor/dev-tool' => '^3.0',
                    ],
                ],
                'composerLock' => [],
            ],
        ]);

        $providerManager = new ProviderManager('test', new ServiceLocator([
            'test' => static fn () => $provider,
        ]));
        $parser = new ComposerJsonAndLock($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

        $parsedData = $parser->execute()->getGroups();

        self::assertSame('', $parsedData['Core']['vendor/a']['project-a']['comment']);
        self::assertSame('', $parsedData['Framework']['vendor/framework']['project-a']['comment']);
        self::assertSame('', $parsedData['Other']['vendor/cache']['project-a']['comment']);
        self::assertSame('', $parsedData['Other']['vendor/http']['project-a']['comment']);
        self::assertSame('', $parsedData['Other']['vendor/log']['project-a']['comment']);
        self::assertSame('', $parsedData['Require Dev']['vendor/dev-tool']['project-a']['comment']);
    }

    public function testExecuteCreatesCompleteObservedCellsWhenVersionIsDisplayedAsValue(): void
    {
        $packageConfig = new PackageConfig([
            'includeInstalledVersion' => true,
            'installedVersionDisplayedIn' => 'value',
            'packageGroups' => [
                ['name' => 'Observed', 'parserPriority' => 10, 'writerOrder' => 10, 'groupType' => 'observed', 'regex' => '/^vendor\/observed$/'],
            ],
            'observedPackages' => ['vendor/observed'],
        ]);

        $repositoryList = new RepositoryList([
            [
                'name' => 'project-a',
                'directory' => 'var/repositories/project-a',
                'remote' => 'git@gitlab.example.com:team/project-a.git',
                'branch' => 'main',
            ],
        ]);

        $provider = new InMemoryProvider([
            'project-a' => [
                'composerJson' => [],
                'composerLock' => [
                    'packages' => [
                        ['name' => 'vendor/observed', 'version' => '4.5.6'],
                    ],
                ],
            ],
        ]);

        $providerManager = new ProviderManager('test', new ServiceLocator([
            'test' => static fn () => $provider,
        ]));
        $parser = new ComposerJsonAndLock($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

        $parsedData = $parser->execute();

        (new ReportValidator())->validate($parsedData);

        self::assertSame(
            ['value' => '4.5.6', 'comment' => ''],
            $parsedData->getGroups()['Observed']['vendor/observed']['project-a']
        );
    }

    public function testExecuteAddsObservedVersionCommentOncePerRepository(): void
    {
        $packageConfig = new PackageConfig([
            'includeInstalledVersion' => true,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                ['name' => 'Observed', 'parserPriority' => 10, 'writerOrder' => 10, 'groupType' => 'observed', 'regex' => '/^vendor\/observed$/'],
            ],
            'observedPackages' => ['vendor/observed'],
        ]);

        $repositoryList = new RepositoryList([
            [
                'name' => 'project-a',
                'directory' => 'var/repositories/project-a',
                'remote' => 'git@gitlab.example.com:team/project-a.git',
                'branch' => 'main',
            ],
            [
                'name' => 'project-b',
                'directory' => 'var/repositories/project-b',
                'remote' => 'git@gitlab.example.com:team/project-b.git',
                'branch' => 'main',
            ],
        ]);

        $provider = new InMemoryProvider([
            'project-a' => [
                'composerJson' => [],
                'composerLock' => [
                    'packages' => [
                        ['name' => 'vendor/observed', 'version' => '1.2.3'],
                    ],
                ],
            ],
            'project-b' => [
                'composerJson' => [],
                'composerLock' => [
                    'packages' => [
                        ['name' => 'vendor/observed', 'version' => '4.5.6'],
                    ],
                ],
            ],
        ]);

        $providerManager = new ProviderManager('test', new ServiceLocator([
            'test' => static fn () => $provider,
        ]));
        $parser = new ComposerJsonAndLock($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

        $parsedData = $parser->execute()->getGroups();

        self::assertSame("Installed version: 1.2.3\n", $parsedData['Observed']['vendor/observed']['project-a']['comment']);
        self::assertSame("Installed version: 4.5.6\n", $parsedData['Observed']['vendor/observed']['project-b']['comment']);
    }

    public function testExecuteIgnoresObservedPackagesWhenLockPackageListsAreMissing(): void
    {
        $packageConfig = new PackageConfig([
            'includeInstalledVersion' => true,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                ['name' => 'Observed', 'parserPriority' => 10, 'writerOrder' => 10, 'groupType' => 'observed', 'regex' => '/^vendor\/observed$/'],
            ],
            'observedPackages' => ['vendor/observed'],
        ]);

        $repositoryList = new RepositoryList([
            [
                'name' => 'project-a',
                'directory' => 'var/repositories/project-a',
                'remote' => 'git@gitlab.example.com:team/project-a.git',
                'branch' => 'main',
            ],
        ]);

        $provider = new InMemoryProvider([
            'project-a' => [
                'composerJson' => [],
                'composerLock' => [
                    'content-hash' => 'metadata-only-lock',
                ],
            ],
        ]);

        $providerManager = new ProviderManager('test', new ServiceLocator([
            'test' => static fn () => $provider,
        ]));
        $parser = new ComposerJsonAndLock($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

        self::assertSame([], $parser->execute()->getGroups());
    }

    protected function parseTransitivePackageGroups(array $repositories): array
    {
        $packageConfig = new PackageConfig([
            'includeInstalledVersion' => true,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                ['name' => 'Packages', 'parserPriority' => 0, 'writerOrder' => 0, 'groupType' => 'require', 'regex' => '/.*/'],
            ],
            'observedPackages' => [],
        ]);
        $repositoryList = new RepositoryList($repositories);
        $provider = new InMemoryProvider([
            'project-a' => [
                'composerJson' => [],
                'composerLock' => [
                    'packages' => [
                        ['name' => 'vendor/transitive', 'version' => '1.2.3'],
                    ],
                ],
            ],
            'project-b' => [
                'composerJson' => [
                    'require' => ['vendor/transitive' => '^1.0'],
                ],
                'composerLock' => [
                    'packages' => [
                        ['name' => 'vendor/transitive', 'version' => '1.2.3'],
                    ],
                ],
            ],
        ]);
        $providerManager = new ProviderManager('test', new ServiceLocator([
            'test' => static fn () => $provider,
        ]));
        $parser = new ComposerJsonAndLock($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

        return $parser->execute()->getGroups();
    }
}
