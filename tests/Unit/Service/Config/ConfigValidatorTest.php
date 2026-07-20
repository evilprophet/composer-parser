<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Config;

use EvilStudio\ComposerParser\Service\Config\ConfigValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConfigValidatorTest extends TestCase
{
    protected const array VALID_STYLING_CONFIG = [
        'groupHeaderBackgroundColor' => '#F2F2F2',
        'cellStyleMapping' => [
            [
                'packageNameRegex' => '/^vendor\//',
                'versionRegex' => '/.*/',
                'color' => '#FF8000',
            ],
        ],
    ];

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
                        'parserPriority' => 0,
                        'writerOrder' => 0,
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
                'styling' => self::VALID_STYLING_CONFIG,
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
                        'parserPriority' => 0,
                        'writerOrder' => 0,
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
                'styling' => self::VALID_STYLING_CONFIG,
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
                        'parserPriority' => 0,
                        'writerOrder' => 0,
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
                        'parserPriority' => 0,
                        'writerOrder' => 0,
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
                        'parserPriority' => 0,
                        'writerOrder' => 0,
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
                        'parserPriority' => 0,
                        'writerOrder' => 0,
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
                        'parserPriority' => 0,
                        'writerOrder' => 0,
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
                'styling' => self::VALID_STYLING_CONFIG,
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
                        'parserPriority' => 0,
                        'writerOrder' => 0,
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

    public function testValidateRejectsInvalidXlsxSheetName(): void
    {
        $validator = new ConfigValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('writer.config.shared.sheetName');

        $validator->validate(
            [
                'providerType' => 'gitRepository',
                'parserType' => 'composerJson',
                'writerType' => 'xlsx',
                'timezone' => 'Europe/Warsaw',
            ],
            [
                'includeInstalledVersion' => false,
                'installedVersionDisplayedIn' => 'comment',
                'packageGroups' => [],
            ],
            [
                'local' => [
                    'fileName' => 'report-{date}',
                    'fileDirectory' => 'var/results',
                ],
                'shared' => [
                    'sheetName' => 'bad/name',
                ],
                'styling' => self::VALID_STYLING_CONFIG,
            ],
            [
                'repositoryList' => [],
            ]
        );
    }

    public function testValidateAllowsSlashInGoogleSheetsSheetName(): void
    {
        $validator = new ConfigValidator();

        $validator->validate(
            [
                'providerType' => 'gitRepository',
                'parserType' => 'composerJson',
                'writerType' => 'googleSheets',
                'timezone' => 'Europe/Warsaw',
            ],
            [
                'includeInstalledVersion' => false,
                'installedVersionDisplayedIn' => 'comment',
                'packageGroups' => [],
            ],
            [
                'local' => [
                    'fileName' => 'report-{date}',
                    'fileDirectory' => 'var/results',
                ],
                'shared' => [
                    'sheetName' => 'valid/google-sheet-name',
                ],
                'styling' => self::VALID_STYLING_CONFIG,
            ],
            [
                'repositoryList' => [],
            ]
        );

        self::assertTrue(true);
    }

    public function testValidateRejectsMalformedStylingConfiguration(): void
    {
        $appConfig = [
            'providerType' => 'gitRepository',
            'parserType' => 'composerJson',
            'writerType' => 'xlsx',
            'timezone' => 'Europe/Warsaw',
        ];
        $packageConfig = [
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [],
        ];
        $baseWriterConfig = [
            'local' => [
                'fileName' => 'report-{date}',
                'fileDirectory' => 'var/results',
            ],
            'shared' => [
                'sheetName' => 'Packages in projects',
            ],
        ];

        foreach (
            [
            'non-array styling' => 'invalid',
            'missing group header color' => ['cellStyleMapping' => []],
            'non-array style mapping' => [
                'groupHeaderBackgroundColor' => '#F2F2F2',
                'cellStyleMapping' => 'invalid',
            ],
            'numeric group header color' => [
                'groupHeaderBackgroundColor' => 999999,
                'cellStyleMapping' => [],
            ],
            'group header color without hash prefix' => [
                'groupHeaderBackgroundColor' => '999999',
                'cellStyleMapping' => [],
            ],
            'missing version regex' => [
                'groupHeaderBackgroundColor' => '#F2F2F2',
                'cellStyleMapping' => [[]],
            ],
            'invalid version regex' => [
                'groupHeaderBackgroundColor' => '#F2F2F2',
                'cellStyleMapping' => [['versionRegex' => '/(/']],
            ],
            'invalid package name regex' => [
                'groupHeaderBackgroundColor' => '#F2F2F2',
                'cellStyleMapping' => [[
                    'versionRegex' => '/.*/',
                    'packageNameRegex' => '/(/',
                ]],
            ],
            'numeric text color' => [
                'groupHeaderBackgroundColor' => '#F2F2F2',
                'cellStyleMapping' => [['versionRegex' => '/.*/', 'color' => 999999]],
            ],
            'background color without hash prefix' => [
                'groupHeaderBackgroundColor' => '#F2F2F2',
                'cellStyleMapping' => [['versionRegex' => '/.*/', 'backgroundColor' => '999999']],
            ],
            'invalid hexadecimal color' => [
                'groupHeaderBackgroundColor' => '#F2F2F2',
                'cellStyleMapping' => [['versionRegex' => '/.*/', 'color' => '#FFFFFG']],
            ],
            ] as $description => $stylingConfig
        ) {
            $writerConfig = $baseWriterConfig;
            $writerConfig['styling'] = $stylingConfig;

            try {
                (new ConfigValidator())->validate(
                    $appConfig,
                    $packageConfig,
                    $writerConfig,
                    ['repositoryList' => []]
                );
                self::fail(sprintf('Expected %s configuration to be rejected.', $description));
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('writer.config.styling', $exception->getMessage());
            }
        }
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
