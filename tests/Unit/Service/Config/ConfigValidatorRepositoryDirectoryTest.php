<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Config;

use EvilStudio\ComposerParser\Service\Config\ConfigValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConfigValidatorRepositoryDirectoryTest extends TestCase
{
    public function testValidateRejectsUnsafeRepositoryDirectories(): void
    {
        foreach (['../outside', '/tmp/project-a', 'var/repositories/../results', 'var/repositories/project-a/'] as $directory) {
            try {
                (new ConfigValidator())->validate(
                    $this->appConfig(),
                    $this->packageConfig(),
                    $this->writerConfig(),
                    $this->repositoryConfig($directory)
                );
                self::fail(sprintf('Expected "%s" to be rejected.', $directory));
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('repository directory', $exception->getMessage());
            }
        }
    }

    protected function appConfig(): array
    {
        return [
            'providerType' => 'gitRepository',
            'parserType' => 'composerJson',
            'writerType' => 'json',
            'timezone' => 'Europe/Warsaw',
        ];
    }

    protected function packageConfig(): array
    {
        return [
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                ['name' => 'All', 'parserPriority' => 0, 'writerOrder' => 0, 'groupType' => 'require', 'regex' => '/.*/'],
            ],
        ];
    }

    protected function writerConfig(): array
    {
        return [
            'local' => ['fileName' => 'report-{date}', 'fileDirectory' => 'var/results'],
            'shared' => ['sheetName' => 'Packages in projects'],
        ];
    }

    protected function repositoryConfig(string $directory): array
    {
        return [
            'repositoryList' => [
                [
                    'name' => 'project-a',
                    'directory' => $directory,
                    'remote' => 'git@gitlab.example.com:team/project-a.git',
                    'branch' => 'main',
                ],
            ],
        ];
    }
}
