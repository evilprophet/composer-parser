<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Service\Writer;

use EvilStudio\ComposerParser\Model\PackageConfig;
use EvilStudio\ComposerParser\Model\ParsedData;
use EvilStudio\ComposerParser\Model\SecurityFinding;
use EvilStudio\ComposerParser\Model\StylingConfig;
use EvilStudio\ComposerParser\Service\Report\ReportFactory;
use EvilStudio\ComposerParser\Service\Report\ReportValidator;
use EvilStudio\ComposerParser\Service\Writer\Html;
use EvilStudio\ComposerParser\Service\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class LocalWritersSecurityTest extends TestCase
{
    protected const string SECURITY_FONT_COLOR = 'FFFFFF';
    protected const string SECURITY_BACKGROUND_COLOR = 'CC0000';
    protected const string FILE_NAME = 'test-report';

    public function testXlsxHighlightsProjectPackageAndVersionCells(): void
    {
        $directory = sys_get_temp_dir() . '/composer-parser-xlsx-security-' . bin2hex(random_bytes(6));
        $filesystem = new Filesystem();

        try {
            $writer = new Xlsx(
                self::FILE_NAME,
                $directory,
                'Packages in projects',
                $this->createPackageConfig(),
                $this->createStylingConfig(),
                new ReportFactory(new ReportValidator())
            );
            $writer->execute($this->createParsedData());

            $sheet = IOFactory::load($directory . DIRECTORY_SEPARATOR . self::FILE_NAME . '.xlsx')->getActiveSheet();

            self::assertSame(self::SECURITY_BACKGROUND_COLOR, $sheet->getStyle([2, 1])->getFill()->getStartColor()->getRGB());
            self::assertSame(self::SECURITY_FONT_COLOR, $sheet->getStyle([2, 1])->getFont()->getColor()->getRGB());
            self::assertTrue($sheet->getStyle([2, 1])->getFont()->getBold());
            self::assertSame(Fill::FILL_NONE, $sheet->getStyle([3, 1])->getFill()->getFillType());

            self::assertSame(self::SECURITY_BACKGROUND_COLOR, $sheet->getStyle([1, 3])->getFill()->getStartColor()->getRGB());
            self::assertSame(self::SECURITY_BACKGROUND_COLOR, $sheet->getStyle([2, 3])->getFill()->getStartColor()->getRGB());
            self::assertSame(Fill::FILL_NONE, $sheet->getStyle([3, 3])->getFill()->getFillType());

            self::assertSame(Fill::FILL_NONE, $sheet->getStyle([1, 4])->getFill()->getFillType());
            self::assertStringContainsString('158 advisories', $sheet->getComment([1, 1])->getText()->getPlainText());
        } finally {
            $filesystem->remove($directory);
        }
    }

    public function testHtmlHighlightsProjectPackageAndVersionCells(): void
    {
        $directory = sys_get_temp_dir() . '/composer-parser-html-security-' . bin2hex(random_bytes(6));
        $filesystem = new Filesystem();

        try {
            $writer = new Html(
                self::FILE_NAME,
                $directory,
                $this->createPackageConfig(),
                $this->createStylingConfig(),
                new ReportFactory(new ReportValidator())
            );
            $writer->execute($this->createParsedData());

            $html = (string) file_get_contents($directory . DIRECTORY_SEPARATOR . self::FILE_NAME . '.html');
            $securityStyle = sprintf('color:#%s;background:#%s', self::SECURITY_FONT_COLOR, self::SECURITY_BACKGROUND_COLOR);

            self::assertStringContainsString(sprintf('<th class="col-project" style="%s">project-a</th>', $securityStyle), $html);
            self::assertStringContainsString('<th class="col-project">project-b</th>', $html);
            self::assertStringContainsString(sprintf('<td class="col-package" style="%s" title="amasty/promo"', $securityStyle), $html);
            self::assertStringContainsString('<td class="col-package" title="vendor/safe"', $html);
            self::assertStringContainsString('158 advisories', $html);
            self::assertSame(3, substr_count($html, 'style="' . $securityStyle . '"'));
        } finally {
            $filesystem->remove($directory);
        }
    }

    protected function createParsedData(): ParsedData
    {
        $groups = [
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
        ];

        $finding = new SecurityFinding(
            'project-a',
            'amasty/promo',
            'MageVulnDB',
            'Amasty_Promo',
            '2.18.0',
            '2.19.0',
            'namespace',
            'Amasty\\Promo',
            SecurityFinding::STATUS_VULNERABLE
        );

        return new ParsedData($groups, ['project-a', 'project-b'], [$finding], 'Security scan (mageVulnDb, 158 advisories)');
    }

    protected function createPackageConfig(): PackageConfig
    {
        return new PackageConfig([
            'packageGroups' => [
                ['name' => 'Extensions', 'parserPriority' => 1, 'writerOrder' => 10, 'groupType' => 'require', 'regex' => '/.*/'],
            ],
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'observedPackages' => [],
        ]);
    }

    protected function createStylingConfig(): StylingConfig
    {
        return new StylingConfig([
            'groupHeaderBackgroundColor' => '999999',
            'cellStyleMapping' => [],
            'securityHighlight' => ['color' => self::SECURITY_FONT_COLOR, 'backgroundColor' => self::SECURITY_BACKGROUND_COLOR],
        ]);
    }
}
