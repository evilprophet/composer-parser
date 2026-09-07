<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Service\Writer;

use EvilStudio\ComposerParser\Model\PackageConfig;
use EvilStudio\ComposerParser\Model\ParsedData;
use EvilStudio\ComposerParser\Model\SecurityFinding;
use EvilStudio\ComposerParser\Model\StylingConfig;
use EvilStudio\ComposerParser\Service\Report\ReportFactory;
use EvilStudio\ComposerParser\Service\Report\ReportValidator;
use EvilStudio\ComposerParser\Service\Writer\GoogleSheets;
use EvilStudio\ComposerParser\Tests\Integration\Support\FakeGoogleSheetsClient;
use PHPUnit\Framework\TestCase;

class GoogleSheetsSecurityTest extends TestCase
{
    protected const string SECURITY_FONT_COLOR = 'FFFFFF';
    protected const string SECURITY_BACKGROUND_COLOR = 'CC0000';

    public function testFlaggedProjectPackageAndVersionCellsAreHighlighted(): void
    {
        $call = $this->write(
            [
                'Extensions' => [
                    'amasty/promo' => [
                        'project-a' => ['value' => '2.18.0', 'comment' => 'MageVulnDB: Amasty_Promo'],
                        'project-b' => ['value' => '2.19.0', 'comment' => ''],
                    ],
                    'vendor/safe' => [
                        'project-a' => ['value' => '1.0.0', 'comment' => ''],
                        'project-b' => ['value' => '1.0.0', 'comment' => ''],
                    ],
                ],
            ],
            ['project-a', 'project-b'],
            [$this->finding('project-a', 'amasty/promo')]
        );

        $stylesByRow = array_column($call['cellStyles'], 'stylesByColumn', 'row');

        self::assertSame($this->securityStyle(), $stylesByRow[1][2]);
        self::assertArrayNotHasKey(3, $stylesByRow[1]);

        self::assertSame($this->securityStyle(), $stylesByRow[3][1]);
        self::assertSame($this->securityStyle(), $stylesByRow[3][2]);
        self::assertArrayNotHasKey(3, $stylesByRow[3]);

        self::assertArrayNotHasKey(4, $stylesByRow);
    }

    public function testSecurityStyleOverridesTheVersionCellStyle(): void
    {
        $call = $this->write(
            ['Extensions' => ['amasty/promo' => ['project-a' => ['value' => 'dev-master', 'comment' => '']]]],
            ['project-a'],
            [$this->finding('project-a', 'amasty/promo')],
            [['versionRegex' => '/dev-/', 'color' => 'FF8000', 'backgroundColor' => 'FF0000']]
        );

        $stylesByRow = array_column($call['cellStyles'], 'stylesByColumn', 'row');

        self::assertSame($this->securityStyle(), $stylesByRow[3][2]);
    }

    public function testSummaryIsAttachedAsANoteOnTheFirstCell(): void
    {
        $call = $this->write(
            ['Extensions' => ['amasty/promo' => ['project-a' => ['value' => '2.18.0', 'comment' => '']]]],
            ['project-a'],
            [$this->finding('project-a', 'amasty/promo')],
            [],
            "Security scan (mageVulnDb, 158 advisories)\nFlagged: 1 packages in 1 projects, 1 findings"
        );

        $notesByRow = array_column($call['notes'], 'notesByColumn', 'row');

        self::assertStringContainsString('158 advisories', $notesByRow[1][1]);
    }

    public function testReportWithoutFindingsCarriesNoSecurityStyleAndNoSummaryNote(): void
    {
        $call = $this->write(
            ['Extensions' => ['vendor/safe' => ['project-a' => ['value' => '1.0.0', 'comment' => '']]]],
            ['project-a'],
            []
        );

        self::assertSame([], $call['cellStyles']);
        self::assertSame([], $call['notes']);
    }

    public function testSyntheticGroupIsWrittenAfterTheConfiguredGroups(): void
    {
        $call = $this->write(
            [
                'Extensions' => ['vendor/safe' => ['project-a' => ['value' => '1.0.0', 'comment' => '']]],
                'Security findings' => ['amasty/promo' => ['project-a' => ['value' => '2.18.0', 'comment' => 'note']]],
            ],
            ['project-a'],
            [$this->finding('project-a', 'amasty/promo')]
        );

        self::assertSame('Extensions', $call['values'][1][0]);
        self::assertSame('vendor/safe', $call['values'][2][0]);
        self::assertSame('Security findings', $call['values'][3][0]);
        self::assertSame('amasty/promo', $call['values'][4][0]);
    }

    protected function write(
        array $groups,
        array $projectNames,
        array $securityFindings,
        array $cellStyleMapping = [],
        string $securitySummary = ''
    ): array {
        $packageConfig = new PackageConfig([
            'packageGroups' => [
                ['name' => 'Extensions', 'parserPriority' => 1, 'writerOrder' => 10, 'groupType' => 'require', 'regex' => '/.*/'],
            ],
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'observedPackages' => [],
        ]);

        $stylingConfig = new StylingConfig([
            'groupHeaderBackgroundColor' => '999999',
            'cellStyleMapping' => $cellStyleMapping,
            'securityHighlight' => ['color' => self::SECURITY_FONT_COLOR, 'backgroundColor' => self::SECURITY_BACKGROUND_COLOR],
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

        $writer->execute(new ParsedData($groups, $projectNames, $securityFindings, $securitySummary));

        return $fakeClient->calls[0];
    }

    protected function finding(string $projectName, string $packageName): SecurityFinding
    {
        return new SecurityFinding(
            $projectName,
            $packageName,
            'MageVulnDB',
            'Amasty_Promo',
            '2.18.0',
            '2.19.0',
            'namespace',
            'Amasty\\Promo',
            SecurityFinding::STATUS_VULNERABLE
        );
    }

    protected function securityStyle(): array
    {
        return ['fontColor' => self::SECURITY_FONT_COLOR, 'backgroundColor' => self::SECURITY_BACKGROUND_COLOR];
    }
}
