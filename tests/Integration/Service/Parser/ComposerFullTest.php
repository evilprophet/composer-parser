<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Service\Parser;

use EvilStudio\ComposerParser\Exception\RepositoryProcessingException;
use EvilStudio\ComposerParser\Model\PackageConfig;
use EvilStudio\ComposerParser\Model\RepositoryList;
use EvilStudio\ComposerParser\Service\Parser\RepositoryDataFactory;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;
use EvilStudio\ComposerParser\Tests\Integration\Support\ComposerFullTestDouble;
use EvilStudio\ComposerParser\Tests\Integration\Support\InMemoryProvider;
use mikehaertl\shellcommand\Command;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ComposerFullTest extends TestCase
{
    public function testComposerCommandUsesRepositoryAsProcessWorkingDirectory(): void
    {
        $parser = $this->parser($this->createStub(Command::class));

        $command = $parser->createDefaultComposerOutdatedCommand('/tmp/repository-directory');

        self::assertSame(
            'composer outdated --no-plugins --no-scripts --format=json',
            $command->getCommand()
        );
        self::assertSame('/tmp/repository-directory', $command->procCwd);
    }

    public function testExecuteAddsLatestAvailableVersionComment(): void
    {
        $parser = $this->parser($this->successfulCommand([
            [
                'name' => 'vendor/package',
                'version' => '1.2.3',
                'latest' => '1.4.0',
                'latest-status' => 'semver-safe-update',
            ],
        ]));

        $comment = $parser->execute()
            ->getGroups()['Packages']['vendor/package']['project-a']['comment'];

        self::assertStringContainsString("Installed version: 1.2.3\n", $comment);
        self::assertStringContainsString("Latest version: 1.4.0\n", $comment);
    }

    public function testExecuteCollectsOnlyFieldsNeededForLatestVersionComments(): void
    {
        $parser = $this->parser($this->successfulCommand([
            [
                'name' => 'vendor/package',
                'version' => '1.2.3',
                'latest' => '1.4.0',
                'latest-status' => 'semver-safe-update',
                'description' => 'This metadata must not remain in the second-phase cache.',
                'source' => ['url' => 'https://example.com/vendor/package.git'],
            ],
        ]));

        $parser->execute();

        self::assertSame([
            'project-a' => [
                'vendor/package' => [
                    'version' => '1.2.3',
                    'latest' => '1.4.0',
                    'latest-status' => 'semver-safe-update',
                ],
            ],
        ], $parser->getCollectedOutdatedPackageDataByProject());
    }

    public function testExecuteAddsLatestAvailableVersionForTransitivePackageIndependentlyOfRepositoryOrder(): void
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
        $outdatedPackages = [
            [
                'name' => 'vendor/transitive',
                'version' => '1.2.3',
                'latest' => '1.4.0',
                'latest-status' => 'semver-safe-update',
            ],
        ];

        $groupsWhenProjectAIsFirst = $this->parseTransitivePackageGroups(
            [$projectA, $projectB],
            $this->successfulCommand($outdatedPackages, 2)
        );
        $groupsWhenProjectBIsFirst = $this->parseTransitivePackageGroups(
            [$projectB, $projectA],
            $this->successfulCommand($outdatedPackages, 2)
        );

        self::assertSame($groupsWhenProjectAIsFirst, $groupsWhenProjectBIsFirst);
        self::assertStringContainsString(
            "Latest version: 1.4.0\n",
            $groupsWhenProjectAIsFirst['Packages']['vendor/transitive']['project-a']['comment']
        );
        self::assertStringContainsString(
            "Latest version: 1.4.0\n",
            $groupsWhenProjectAIsFirst['Packages']['vendor/transitive']['project-b']['comment']
        );
    }

    public function testExecuteSkipsLatestVersionCommentForUpToDatePackage(): void
    {
        $parser = $this->parser($this->successfulCommand([
            [
                'name' => 'vendor/package',
                'version' => '1.2.3',
                'latest' => '1.2.3',
                'latest-status' => 'up-to-date',
            ],
        ]));

        $comment = $parser->execute()
            ->getGroups()['Packages']['vendor/package']['project-a']['comment'];

        self::assertStringContainsString("Installed version: 1.2.3\n", $comment);
        self::assertStringNotContainsString('Latest version:', $comment);
    }

    public function testExecuteRejectsComposerCommandFailure(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects(self::once())->method('execute')->willReturn(false);
        $command->expects(self::once())->method('getError')->willReturn('composer executable was not found');

        $this->expectParserFailure(
            $this->parser($command),
            'Unable to inspect outdated packages in "/tmp/project-a": composer executable was not found'
        );
    }

    public function testExecuteRejectsMalformedComposerOutput(): void
    {
        $command = $this->createMock(Command::class);
        $command->expects(self::once())->method('execute')->willReturn(true);
        $command->expects(self::once())->method('getOutput')->willReturn('{');

        $this->expectParserFailure(
            $this->parser($command),
            'Composer returned invalid outdated package data for "/tmp/project-a"'
        );
    }

    public function testExecuteRejectsIncompletePackageData(): void
    {
        $parser = $this->parser($this->successfulCommand([
            [
                'name' => 'vendor/package',
                'version' => '1.2.3',
                'latest-status' => 'semver-safe-update',
            ],
        ]));

        $this->expectParserFailure(
            $parser,
            'package at index 0 requires string field "latest"'
        );
    }

    protected function parser(Command $command): ComposerFullTestDouble
    {
        return $this->parserForRepositories($command, [
            [
                'name' => 'project-a',
                'directory' => 'var/repositories/project-a',
                'remote' => 'git@gitlab.example.com:team/project-a.git',
                'branch' => 'main',
            ],
        ], [
            'project-a' => [
                'composerJson' => [
                    'require' => ['vendor/package' => '^1.0'],
                ],
                'composerLock' => [
                    'packages' => [
                        ['name' => 'vendor/package', 'version' => '1.2.3'],
                    ],
                ],
            ],
        ], '/tmp/project-a');
    }

    protected function parseTransitivePackageGroups(array $repositories, Command $command): array
    {
        return $this->parserForRepositories($command, $repositories, [
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
        ], '/tmp/transitive-package')->execute()->getGroups();
    }

    protected function parserForRepositories(Command $command, array $repositories, array $repositoryData, string $localRepositoryDirectory): ComposerFullTestDouble
    {
        $packageConfig = new PackageConfig([
            'includeInstalledVersion' => true,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                [
                    'name' => 'Packages',
                    'parserPriority' => 0,
                    'writerOrder' => 0,
                    'groupType' => 'require',
                    'regex' => '/.*/',
                ],
            ],
            'observedPackages' => [],
        ]);
        $repositoryList = new RepositoryList($repositories);
        $provider = new InMemoryProvider($repositoryData, $localRepositoryDirectory);
        $providerManager = new ProviderManager('test', new ServiceLocator([
            'test' => static fn () => $provider,
        ]));
        $parser = new ComposerFullTestDouble(
            $packageConfig,
            $repositoryList,
            $providerManager,
            new RepositoryDataFactory()
        );
        $parser->setComposerOutdatedCommand($command);

        return $parser;
    }

    protected function successfulCommand(array $outdatedPackages, int $executionCount = 1): Command
    {
        $command = $this->createMock(Command::class);
        $command->expects(self::exactly($executionCount))->method('execute')->willReturn(true);
        $command->expects(self::exactly($executionCount))
            ->method('getOutput')
            ->willReturn((string) json_encode(['installed' => $outdatedPackages], JSON_THROW_ON_ERROR));

        return $command;
    }

    protected function expectParserFailure(ComposerFullTestDouble $parser, string $expectedMessage): void
    {
        try {
            $parser->execute();
            self::fail('Expected ComposerFull parser execution to fail.');
        } catch (RepositoryProcessingException $exception) {
            self::assertStringContainsString($expectedMessage, $exception->getPrevious()?->getMessage() ?? '');
        }
    }
}
