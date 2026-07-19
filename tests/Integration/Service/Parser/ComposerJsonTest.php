<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Service\Parser;

use EvilStudio\ComposerParser\Exception\RepositoryProcessingException;
use EvilStudio\ComposerParser\Model\PackageConfig;
use EvilStudio\ComposerParser\Model\RepositoryList;
use EvilStudio\ComposerParser\Service\Parser\ComposerJson;
use EvilStudio\ComposerParser\Service\Parser\RepositoryDataFactory;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;
use EvilStudio\ComposerParser\Tests\Integration\Support\FailingInMemoryProvider;
use EvilStudio\ComposerParser\Tests\Integration\Support\InMemoryProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use UnexpectedValueException;

class ComposerJsonTest extends TestCase
{
    public function testExecuteParsesRequireAndRequireDevWithProjectMatrix(): void
    {
        $packageConfig = new PackageConfig([
            'includeInstalledVersion' => false,
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
                'name' => 'project-b',
                'directory' => 'var/repositories/project-b',
                'remote' => 'git@gitlab.example.com:team/project-b.git',
                'branch' => 'main',
            ],
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
                    'require-dev' => ['vendor/dev-tool' => '^3.0'],
                ],
            ],
            'project-b' => [
                'composerJson' => [
                    'require' => [
                        'vendor/a' => '^1.1',
                        'vendor/framework' => '^9.3',
                        'vendor/cache' => '^3.2',
                        'vendor/http' => '^7.9',
                        'vendor/log' => '^2.1',
                    ],
                    'require-dev' => ['vendor/dev-tool' => '^3.1'],
                ],
            ],
        ]);

        $providerManager = new ProviderManager('test', new ServiceLocator([
            'test' => static fn () => $provider,
        ]));
        $parser = new ComposerJson($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

        $parsedData = $parser->execute();
        $data = $parsedData->getGroups();

        self::assertSame(['project-a', 'project-b'], $parsedData->getProjectNames());
        self::assertSame('^1.0', $data['Core']['vendor/a']['project-a']['value']);
        self::assertSame('^1.1', $data['Core']['vendor/a']['project-b']['value']);
        self::assertSame('^9.2', $data['Framework']['vendor/framework']['project-a']['value']);
        self::assertSame('^9.3', $data['Framework']['vendor/framework']['project-b']['value']);
        self::assertSame('^3.1', $data['Other']['vendor/cache']['project-a']['value']);
        self::assertSame('^3.2', $data['Other']['vendor/cache']['project-b']['value']);
        self::assertSame('^7.8', $data['Other']['vendor/http']['project-a']['value']);
        self::assertSame('^7.9', $data['Other']['vendor/http']['project-b']['value']);
        self::assertSame('^2.0', $data['Other']['vendor/log']['project-a']['value']);
        self::assertSame('^2.1', $data['Other']['vendor/log']['project-b']['value']);
        self::assertSame('^3.0', $data['Require Dev']['vendor/dev-tool']['project-a']['value']);
        self::assertSame('^3.1', $data['Require Dev']['vendor/dev-tool']['project-b']['value']);
    }

    public function testExecuteAddsProjectContextToRepositoryFailure(): void
    {
        $packageConfig = new PackageConfig([
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                ['name' => 'Core', 'parserPriority' => 10, 'writerOrder' => 10, 'groupType' => 'require', 'regex' => '/.*/'],
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
            [
                'name' => 'project-b',
                'directory' => 'var/repositories/project-b',
                'remote' => 'git@gitlab.example.com:team/project-b.git',
                'branch' => 'main',
            ],
        ]);

        $provider = new FailingInMemoryProvider([
            'project-a' => [
                'composerJson' => ['require' => ['vendor/a' => '^1.0']],
            ],
            'project-b' => [
                'composerJson' => ['require' => ['vendor/b' => '^1.0']],
            ],
        ], 'project-b');

        $providerManager = new ProviderManager('test', new ServiceLocator([
            'test' => static fn () => $provider,
        ]));
        $parser = new ComposerJson($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

        try {
            $parser->execute();
            self::fail('Expected repository processing to fail.');
        } catch (RepositoryProcessingException $exception) {
            self::assertSame('project-b', $exception->getProjectName());
            self::assertStringContainsString('Failed to process repository "project-b"', $exception->getMessage());
            self::assertSame('Repository data is unavailable.', $exception->getPrevious()?->getMessage());
        }
    }

    public function testExecuteParsesReplacePackages(): void
    {
        $packageConfig = new PackageConfig([
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                ['name' => 'Replace', 'parserPriority' => 10, 'writerOrder' => 10, 'groupType' => 'replace', 'regex' => '/.*/'],
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
                    'replace' => ['vendor/replaced-package' => 'self.version'],
                ],
            ],
        ]);
        $providerManager = new ProviderManager('test', new ServiceLocator([
            'test' => static fn () => $provider,
        ]));
        $parser = new ComposerJson($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

        self::assertSame(
            ['value' => 'self.version', 'comment' => ''],
            $parser->execute()->getGroups()['Replace']['vendor/replaced-package']['project-a']
        );
    }

    public function testExecuteParsesPatchSetEntries(): void
    {
        $packageConfig = new PackageConfig([
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                ['name' => 'Patchset', 'parserPriority' => 10, 'writerOrder' => 10, 'groupType' => 'patchset', 'regex' => '/^vendor\/package$/'],
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
                    'extra' => [
                        'patchset' => [
                            'vendor/package' => [
                                ['filename' => 'patches/constrained.patch', 'version-constraint' => '^2.0'],
                                ['filename' => 'patches/unconstrained.patch'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $providerManager = new ProviderManager('test', new ServiceLocator([
            'test' => static fn () => $provider,
        ]));
        $parser = new ComposerJson($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

        $parsedData = $parser->execute()->getGroups();

        self::assertSame('^2.0', $parsedData['Patchset']['constrained.patch']['project-a']['value']);
        self::assertSame('*', $parsedData['Patchset']['unconstrained.patch']['project-a']['value']);
    }

    public function testExecuteRejectsMalformedPatchSetEntries(): void
    {
        $packageConfig = new PackageConfig([
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                ['name' => 'Patchset', 'parserPriority' => 10, 'writerOrder' => 10, 'groupType' => 'patchset', 'regex' => '/.*/'],
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

        foreach (['not-an-array', []] as $invalidPatch) {
            $provider = new InMemoryProvider([
                'project-a' => [
                    'composerJson' => [
                        'extra' => [
                            'patchset' => [
                                'vendor/package' => [$invalidPatch],
                            ],
                        ],
                    ],
                ],
            ]);
            $providerManager = new ProviderManager('test', new ServiceLocator([
                'test' => static fn () => $provider,
            ]));
            $parser = new ComposerJson($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

            try {
                $parser->execute();
                self::fail('Expected malformed patchset entry to fail repository processing.');
            } catch (RepositoryProcessingException $exception) {
                self::assertInstanceOf(UnexpectedValueException::class, $exception->getPrevious());
                self::assertStringContainsString('filename must be a non-empty string', $exception->getPrevious()->getMessage());
            }
        }
    }
}
