<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Service\Writer;

use EvilStudio\ComposerParser\Model\PackageConfig;
use EvilStudio\ComposerParser\Model\ParsedData;
use EvilStudio\ComposerParser\Model\StylingConfig;
use EvilStudio\ComposerParser\Service\Report\ReportFactory;
use EvilStudio\ComposerParser\Service\Report\ReportValidator;
use EvilStudio\ComposerParser\Service\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class XlsxTest extends TestCase
{
    public function testExecuteAlignsIncompleteRowsWithProjectHeaders(): void
    {
        $directory = sys_get_temp_dir() . '/composer-parser-xlsx-writer-' . bin2hex(random_bytes(6));
        $fileName = 'test-report';
        $filePath = $directory . DIRECTORY_SEPARATOR . $fileName . '.xlsx';

        $packageConfig = new PackageConfig([
            'packageGroups' => [
                ['name' => 'Observed', 'parserPriority' => 10, 'writerOrder' => 10, 'groupType' => 'observed', 'regex' => '/.*/'],
            ],
            'includeInstalledVersion' => true,
            'installedVersionDisplayedIn' => 'comment',
            'observedPackages' => ['vendor/package'],
        ]);
        $stylingConfig = new StylingConfig([
            'groupHeaderBackgroundColor' => '999999',
            'cellStyleMapping' => [],
        ]);
        $writer = new Xlsx(
            $fileName,
            $directory,
            'Packages in projects',
            $packageConfig,
            $stylingConfig,
            new ReportFactory(new ReportValidator())
        );
        $parsedData = new ParsedData(
            [
                'Observed' => [
                    'vendor/package' => [
                        'zeta' => ['value' => '2.0.0', 'comment' => 'Installed version: 2.0.0'],
                    ],
                ],
            ],
            ['alpha', 'zeta']
        );

        $writer->execute($parsedData);

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        self::assertSame('alpha', $sheet->getCell('B1')->getValue());
        self::assertSame('zeta', $sheet->getCell('C1')->getValue());
        self::assertSame('', (string) $sheet->getCell('B3')->getValue());
        self::assertSame('2.0.0', $sheet->getCell('C3')->getValue());
        self::assertSame(
            'Installed version: 2.0.0',
            $sheet->getComment('C3')->getText()->getPlainText()
        );

        $spreadsheet->disconnectWorksheets();
        (new Filesystem())->remove($directory);
    }

    public function testExecuteStylesGroupHeaderAcrossEveryProjectColumn(): void
    {
        $directory = sys_get_temp_dir() . '/composer-parser-xlsx-writer-' . bin2hex(random_bytes(6));
        $fileName = 'wide-report';
        $filePath = $directory . DIRECTORY_SEPARATOR . $fileName . '.xlsx';
        $projectNames = array_map(
            static fn (int $projectNumber): string => sprintf('project-%d', $projectNumber),
            range(1, 26)
        );

        $packageConfig = new PackageConfig([
            'packageGroups' => [
                ['name' => 'Packages', 'parserPriority' => 10, 'writerOrder' => 10, 'groupType' => 'require', 'regex' => '/.*/'],
            ],
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'observedPackages' => [],
        ]);
        $writer = new Xlsx(
            $fileName,
            $directory,
            'Packages in projects',
            $packageConfig,
            new StylingConfig([
                'groupHeaderBackgroundColor' => '999999',
                'cellStyleMapping' => [],
            ]),
            new ReportFactory(new ReportValidator())
        );
        $parsedData = new ParsedData([
            'Packages' => [
                'vendor/package' => [
                    'project-26' => ['value' => '^2.0', 'comment' => ''],
                ],
            ],
        ], $projectNames);

        $writer->execute($parsedData);

        $spreadsheet = IOFactory::load($filePath);
        self::assertSame(
            '999999',
            $spreadsheet->getActiveSheet()->getStyle('AA2')->getFill()->getStartColor()->getRGB()
        );

        $spreadsheet->disconnectWorksheets();
        (new Filesystem())->remove($directory);
    }
}
