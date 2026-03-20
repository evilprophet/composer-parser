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
                'timezone' => 'Europe/Warsaw',
                'gitlab' => ['url' => 'https://gitlab.example.com', 'apiToken' => 'token'],
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
                'shared' => [
                    'sheetName' => 'Packages in projects',
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
                'timezone' => 'Europe/Warsaw',
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
                'shared' => [
                    'sheetName' => 'Packages in projects',
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
                'timezone' => 'Europe/Warsaw',
                'gitlab' => ['url' => 'https://gitlab.example.com', 'apiToken' => 'token'],
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
                'shared' => [
                    'sheetName' => 'Packages in projects',
                ],
            ],
            [
                'repositoryList' => [],
            ]
        );
    }

    public function testValidateRejectsInvalidTimezoneIdentifier(): void
    {
        $validator = new ConfigValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('app.config.timezone');

        $validator->validate(
            [
                'providerType' => 'gitlabApiFiles',
                'parserType' => 'composerJsonAndLock',
                'writerType' => 'xlsx',
                'timezone' => 'Mars/Phobos',
                'gitlab' => ['url' => 'https://gitlab.example.com', 'apiToken' => 'token'],
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
                'shared' => [
                    'sheetName' => 'Packages in projects',
                ],
            ],
            [
                'repositoryList' => [],
            ]
        );
    }

    public function testValidateRejectsMissingGitlabApiTokenForGitlabProvider(): void
    {
        $validator = new ConfigValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('app.config.gitlab.apiToken');

        $validator->validate(
            [
                'providerType' => 'gitlabApiFiles',
                'parserType' => 'composerJsonAndLock',
                'writerType' => 'xlsx',
                'timezone' => 'Europe/Warsaw',
                'gitlab' => ['url' => 'https://gitlab.example.com', 'apiToken' => ''],
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
                'shared' => [
                    'sheetName' => 'Packages in projects',
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
                'timezone' => 'Europe/Warsaw',
                'gitlab' => ['url' => 'https://gitlab.example.com', 'apiToken' => 'token'],
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
                'shared' => [
                    'sheetName' => 'Packages in projects',
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
                'timezone' => 'Europe/Warsaw',
                'gitlab' => ['url' => 'https://gitlab.example.com', 'apiToken' => 'token'],
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
                'shared' => [
                    'sheetName' => 'Packages in projects',
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

    public function testValidateRejectsMissingSheetName(): void
    {
        $validator = new ConfigValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('writer.config.shared.sheetName');

        $validator->validate(
            [
                'providerType' => 'gitlabApiFiles',
                'parserType' => 'composerJsonAndLock',
                'writerType' => 'xlsx',
                'timezone' => 'Europe/Warsaw',
                'gitlab' => ['url' => 'https://gitlab.example.com', 'apiToken' => 'token'],
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
                'shared' => [],
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
    }

    public function testValidateGoogleSheetsWriterConfigAcceptsValidConfiguration(): void
    {
        $validator = new ConfigValidator();

        $validator->validateGoogleSheetsWriterConfig([
            'googleSheets' => [
                'spreadsheetId' => 'spreadsheet-id',
                'serviceAccountJsonPath' => '/tmp/google-service-account.json',
            ],
        ]);

        self::assertTrue(true);
    }

    public function testValidateGoogleSheetsWriterConfigRejectsMissingSpreadsheetId(): void
    {
        $validator = new ConfigValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('writer.config.googleSheets.spreadsheetId');

        $validator->validateGoogleSheetsWriterConfig([
            'googleSheets' => [
                'spreadsheetId' => '',
                'serviceAccountJsonPath' => '/tmp/google-service-account.json',
            ],
        ]);
    }
}
