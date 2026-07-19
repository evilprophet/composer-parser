<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Service\Parser;

use EvilStudio\ComposerParser\Model\PackageConfig;
use EvilStudio\ComposerParser\Model\RepositoryList;
use EvilStudio\ComposerParser\Service\Parser\ComposerJsonAndLock;
use EvilStudio\ComposerParser\Service\Parser\RepositoryDataFactory;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;
use EvilStudio\ComposerParser\Tests\Integration\Support\FixtureFileProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ComposerJsonAndLockFileProviderTest extends TestCase
{
    public function testExecuteReadsComposerFilesFromFixtureDirectory(): void
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
                'directory' => 'var/repositories/repo-a',
                'remote' => 'git@gitlab.example.com:team/repo-a.git',
                'branch' => 'main',
            ],
        ]);

        $provider = new FixtureFileProvider(__DIR__ . '/../../Fixtures');
        $providerManager = new ProviderManager('fixture', new ServiceLocator([
            'fixture' => static fn () => $provider,
        ]));
        $parser = new ComposerJsonAndLock($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());

        $data = $parser->execute()->getGroups();

        self::assertSame('^1.0', $data['Core']['vendor/a']['project-a']['value']);
        self::assertSame('^9.2', $data['Framework']['vendor/framework']['project-a']['value']);
        self::assertSame('^3.1', $data['Other']['vendor/cache']['project-a']['value']);
        self::assertSame('^7.8', $data['Other']['vendor/http']['project-a']['value']);
        self::assertSame('^2.0', $data['Other']['vendor/log']['project-a']['value']);
        self::assertSame('^3.0', $data['Require Dev']['vendor/dev-tool']['project-a']['value']);
        self::assertStringContainsString('Installed version: 1.2.3', $data['Core']['vendor/a']['project-a']['comment']);
        self::assertStringContainsString('Installed version: 9.2.1', $data['Framework']['vendor/framework']['project-a']['comment']);
    }
}
