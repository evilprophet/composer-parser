<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Service\Writer;

use EvilStudio\ComposerParser\Model\PackageConfig;
use EvilStudio\ComposerParser\Model\ParsedData;
use EvilStudio\ComposerParser\Model\StylingConfig;
use EvilStudio\ComposerParser\Service\Report\ReportFactory;
use EvilStudio\ComposerParser\Service\Report\ReportValidator;
use EvilStudio\ComposerParser\Service\Writer\GoogleSheets;
use EvilStudio\ComposerParser\Tests\Integration\Support\FakeGoogleSheetsClient;
use PHPUnit\Framework\TestCase;

class GoogleSheetsTest extends TestCase
{
    public function testExecuteBuildsOrderedRowsStylesAndNotes(): void
    {
        $packageConfig = new PackageConfig([
            'packageGroups' => [
                ['name' => 'Group B', 'parserPriority' => 1, 'writerOrder' => 20, 'groupType' => 'require', 'regex' => '/.*/'],
                ['name' => 'Group A', 'parserPriority' => 2, 'writerOrder' => 10, 'groupType' => 'require', 'regex' => '/.*/'],
            ],
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'observedPackages' => [],
        ]);

        $stylingConfig = new StylingConfig([
            'groupHeaderBackgroundColor' => '999999',
            'cellStyleMapping' => [
                ['versionRegex' => '/\^1\.0/', 'color' => 'FF0000', 'backgroundColor' => 'FFFF00'],
            ],
        ]);

        $fakeClient = new FakeGoogleSheetsClient();
        $writer = new GoogleSheets(
            'Packages in projects',
            'spreadsheet-1',
            '/tmp/key.json',
            $packageConfig,
            $stylingConfig,
            new ReportFactory(new ReportValidator()),
            $fakeClient
        );

        $parsedData = new ParsedData(
            [
                'Group B' => [
                    'vendor/z' => [
                        'project-a' => ['value' => '^2.0', 'comment' => ''],
                    ],
                ],
                'Group A' => [
                    'vendor/a' => [
                        'project-a' => ['value' => '^1.0', 'comment' => 'Installed version: 1.2.3'],
                    ],
                ],
            ],
            ['project-a']
        );

        $writer->execute($parsedData);

        self::assertCount(1, $fakeClient->calls);
        $call = $fakeClient->calls[0];

        self::assertSame('spreadsheet-1', $call['spreadsheetId']);
        self::assertSame('Packages in projects', $call['sheetName']);
        self::assertStringStartsWith('Last update: ', $call['values'][0][0]);
        self::assertSame('project-a', $call['values'][0][1]);
        self::assertSame('Group A', $call['values'][1][0]);
        self::assertSame('vendor/a', $call['values'][2][0]);
        self::assertSame('Group B', $call['values'][3][0]);
        self::assertSame('vendor/z', $call['values'][4][0]);
        self::assertSame([2, 4], $call['groupRows']);
        self::assertSame('999999', $call['groupHeaderBackgroundColor']);
        self::assertSame('FF0000', $call['cellStyles'][0]['stylesByColumn'][2]['fontColor']);
        self::assertSame('FFFF00', $call['cellStyles'][0]['stylesByColumn'][2]['backgroundColor']);
        self::assertSame('Installed version: 1.2.3', $call['notes'][0]['notesByColumn'][2]);
    }
}
