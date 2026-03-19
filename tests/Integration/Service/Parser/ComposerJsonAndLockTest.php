<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Service\Parser;

use EvilStudio\ComposerParser\Model\PackageConfig;
use EvilStudio\ComposerParser\Model\RepositoryList;
use EvilStudio\ComposerParser\Service\Parser\ComposerJsonAndLock;
use EvilStudio\ComposerParser\Service\Parser\RepositoryDataFactory;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;
use EvilStudio\ComposerParser\Tests\Integration\Support\InMemoryProvider;
use PHPUnit\Framework\TestCase;

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
                ],
            ],
        ]);

        $providerManager = new ProviderManager('test', ['test' => $provider]);
        $parser = new ComposerJsonAndLock($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

        $parsedData = $parser->execute()->getProjectsData();
        self::assertStringContainsString('Installed version: 1.2.3', $parsedData['Core']['vendor/a']['project-a']['comment']);
        self::assertStringContainsString('Installed version: 9.2.1', $parsedData['Framework']['vendor/framework']['project-a']['comment']);
        self::assertStringContainsString('Installed version: 3.1.4', $parsedData['Other']['vendor/cache']['project-a']['comment']);
        self::assertStringContainsString('Installed version: 7.8.2', $parsedData['Other']['vendor/http']['project-a']['comment']);
        self::assertStringContainsString('Installed version: 2.0.0', $parsedData['Other']['vendor/log']['project-a']['comment']);
        self::assertSame('', $parsedData['Require Dev']['vendor/dev-tool']['project-a']['comment']);
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

        $providerManager = new ProviderManager('test', ['test' => $provider]);
        $parser = new ComposerJsonAndLock($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

        $parsedData = $parser->execute()->getProjectsData();

        self::assertSame('', $parsedData['Core']['vendor/a']['project-a']['comment']);
        self::assertSame('', $parsedData['Framework']['vendor/framework']['project-a']['comment']);
        self::assertSame('', $parsedData['Other']['vendor/cache']['project-a']['comment']);
        self::assertSame('', $parsedData['Other']['vendor/http']['project-a']['comment']);
        self::assertSame('', $parsedData['Other']['vendor/log']['project-a']['comment']);
        self::assertSame('', $parsedData['Require Dev']['vendor/dev-tool']['project-a']['comment']);
    }
}
