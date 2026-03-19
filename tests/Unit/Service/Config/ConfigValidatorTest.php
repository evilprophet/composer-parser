<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Config;

use EvilStudio\ComposerParser\Service\Config\ConfigValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConfigValidatorTest extends TestCase
{
    public function testValidateAcceptsValidConfiguration(): void
    {
        $validator = new ConfigValidator();

        $validator->validate(
            [
                'providerType' => 'gitlabApiFiles',
                'parserType' => 'composerJsonAndLock',
                'writerType' => 'xlsx',
            ],
            [
                'includeInstalledVersion' => true,
                'installedVersionDisplayedIn' => 'comment',
                'packageGroups' => [
                    [
                        'name' => 'All',
                        'groupType' => 'require',
                        'regex' => '/.*/',
                    ],
                ],
            ],
            [
                'local' => [
                    'fileName' => 'report-{date}',
                    'fileDirectory' => 'var/results',
                ],
            ],
            [
                'repositoryList' => [
                    [
                        'name' => 'project-a',
                        'directory' => 'var/repositories/project-a',
                        'remote' => 'git@gitlab.example.com:team/project-a.git',
                        'branch' => 'main',
                    ],
                ],
            ]
        );

        self::assertTrue(true);
    }

    public function testValidateRejectsUnsupportedProviderType(): void
    {
        $validator = new ConfigValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('app.config.providerType');

        $validator->validate(
            [
                'providerType' => 'unsupported',
                'parserType' => 'composerJsonAndLock',
                'writerType' => 'xlsx',
            ],
            [
                'includeInstalledVersion' => true,
                'installedVersionDisplayedIn' => 'comment',
                'packageGroups' => [
                    [
                        'name' => 'All',
                        'groupType' => 'require',
                        'regex' => '/.*/',
                    ],
                ],
            ],
            [
                'local' => [
                    'fileName' => 'report-{date}',
                    'fileDirectory' => 'var/results',
                ],
            ],
            [
                'repositoryList' => [],
            ]
        );
    }

    public function testValidateRejectsInvalidRegex(): void
    {
        $validator = new ConfigValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('regex');

        $validator->validate(
            [
                'providerType' => 'gitlabApiFiles',
                'parserType' => 'composerJsonAndLock',
                'writerType' => 'xlsx',
            ],
            [
                'includeInstalledVersion' => true,
                'installedVersionDisplayedIn' => 'comment',
                'packageGroups' => [
                    [
                        'name' => 'All',
                        'groupType' => 'require',
                        'regex' => '/(/',
                    ],
                ],
            ],
            [
                'local' => [
                    'fileName' => 'report-{date}',
                    'fileDirectory' => 'var/results',
                ],
            ],
            [
                'repositoryList' => [],
            ]
        );
    }

    public function testValidateAcceptsJsonWriterType(): void
    {
        $validator = new ConfigValidator();

        $validator->validate(
            [
                'providerType' => 'gitlabApiFiles',
                'parserType' => 'composerJsonAndLock',
                'writerType' => 'json',
            ],
            [
                'includeInstalledVersion' => true,
                'installedVersionDisplayedIn' => 'comment',
                'packageGroups' => [
                    [
                        'name' => 'All',
                        'groupType' => 'require',
                        'regex' => '/.*/',
                    ],
                ],
            ],
            [
                'local' => [
                    'fileName' => 'report-{date}',
                    'fileDirectory' => 'var/results',
                ],
            ],
            [
                'repositoryList' => [
                    [
                        'name' => 'project-a',
                        'directory' => 'var/repositories/project-a',
                        'remote' => 'git@gitlab.example.com:team/project-a.git',
                        'branch' => 'main',
                    ],
                ],
            ]
        );

        self::assertTrue(true);
    }

    public function testValidateAcceptsHtmlWriterType(): void
    {
        $validator = new ConfigValidator();

        $validator->validate(
            [
                'providerType' => 'gitlabApiFiles',
                'parserType' => 'composerJsonAndLock',
                'writerType' => 'html',
            ],
            [
                'includeInstalledVersion' => true,
                'installedVersionDisplayedIn' => 'comment',
                'packageGroups' => [
                    [
                        'name' => 'All',
                        'groupType' => 'require',
                        'regex' => '/.*/',
                    ],
                ],
            ],
            [
                'local' => [
                    'fileName' => 'report-{date}',
                    'fileDirectory' => 'var/results',
                ],
            ],
            [
                'repositoryList' => [
                    [
                        'name' => 'project-a',
                        'directory' => 'var/repositories/project-a',
                        'remote' => 'git@gitlab.example.com:team/project-a.git',
                        'branch' => 'main',
                    ],
                ],
            ]
        );

        self::assertTrue(true);
    }
}
