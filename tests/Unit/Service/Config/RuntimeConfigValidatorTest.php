<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Config;

use EvilStudio\ComposerParser\Service\Config\ConfigValidator;
use EvilStudio\ComposerParser\Service\Config\RuntimeConfigValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RuntimeConfigValidatorTest extends TestCase
{
    public function testCleanupOnlyRequiresRepositoryConfiguration(): void
    {
        $validator = new RuntimeConfigValidator(
            new ConfigValidator(),
            'not-needed-for-cleanup',
            null,
            null,
            $this->repositoryConfig()
        );

        $validator->validateForCleanup();

        self::assertTrue(true);
    }

    public function testRunRejectsMalformedTopLevelConfiguration(): void
    {
        $validator = new RuntimeConfigValidator(
            new ConfigValidator(),
            'not-an-array',
            $this->packageConfig(),
            $this->writerConfig(),
            $this->repositoryConfig()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('app.config must be an array');

        $validator->validateForRun();
    }

    protected function packageConfig(): array
    {
        return [
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                ['name' => 'All', 'parserPriority' => 0, 'writerOrder' => 0, 'groupType' => 'require', 'regex' => '/.*/'],
            ],
            'observedPackages' => [],
        ];
    }

    protected function writerConfig(): array
    {
        return [
            'local' => ['fileName' => 'report', 'fileDirectory' => 'var/results'],
            'shared' => ['sheetName' => 'Packages'],
            'googleSheets' => [],
        ];
    }

    protected function repositoryConfig(): array
    {
        return [
            'repositoryList' => [
                [
                    'name' => 'project-a',
                    'directory' => 'var/repositories/project-a',
                    'remote' => 'git@gitlab.example.com:team/project-a.git',
                    'branch' => 'main',
                ],
            ],
        ];
    }
}
