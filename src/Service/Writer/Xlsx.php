<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Writer;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\Data\StylingConfigInterface;
use EvilStudio\ComposerParser\Api\WriterInterface;
use EvilStudio\ComposerParser\Model\Report;
use EvilStudio\ComposerParser\Service\Report\ReportFactory;
use EvilStudio\ComposerParser\Service\Writer\Support\HandlesLocalOutputPath;
use EvilStudio\ComposerParser\Service\Writer\Support\ResolvesVersionCellStyle;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxSpreadsheet;

class Xlsx implements WriterInterface
{
    use HandlesLocalOutputPath;
    use ResolvesVersionCellStyle;

    protected const string FILE_EXTENSION = '.xlsx';

    protected Spreadsheet $spreadsheet;

    public function __construct(
        protected string $fileName,
        protected string $fileDirectory,
        protected string $sheetName,
        protected PackageConfigInterface $packageConfig,
        protected StylingConfigInterface $stylingConfig,
        protected ReportFactory $reportFactory
    ) {}

    public function execute(ParsedDataInterface $parsedData): void
    {
        $report = $this->reportFactory->build($parsedData);

        $this->prepareSpreadsheet();

        $this->prepareHeader($report->getProjectNames());
        $this->prepareData($report);

        $this->writeSpreadsheet();
    }

    protected function prepareSpreadsheet(): void
    {
        $this->spreadsheet = new Spreadsheet();
        $this->spreadsheet->getActiveSheet()->setTitle($this->normalizeSheetTitle($this->sheetName));

        $this->spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
        $this->spreadsheet->getDefaultStyle()->getFont()->setSize(10);
    }

    protected function writeSpreadsheet(): void
    {
        $filePath = $this->getFilePath();

        $xlsxWriter = new XlsxSpreadsheet($this->spreadsheet);
        $xlsxWriter->save($filePath);
    }

    protected function prepareHeader(array $projectNames): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();

        $currentDate = date('Y-m-d H:i');
        $sheet->getColumnDimensionByColumn(1)->setAutoSize(true);
        $sheet->setCellValue([1, 1], sprintf('Last update: %s', $currentDate));

        $column = 2;
        foreach ($projectNames as $projectName) {
            $sheet->setCellValue([$column, 1], $projectName);
            $sheet->getStyle([$column, 1])->applyFromArray($this->getHeaderStyle());
            $sheet->getColumnDimensionByColumn($column)->setWidth(10);
            $column++;
        }
    }

    protected function prepareData(Report $report): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        $packageGroups = $this->packageConfig->getPackageGroupsForWriter();

        $parsedComposerJson = $report->getGroups();

        $column = 1;
        $row = 2;
        foreach ($packageGroups as $packageGroup) {
            if (!array_key_exists($packageGroup['name'], $parsedComposerJson)) {
                continue;
            }

            $currentGroup = $parsedComposerJson[$packageGroup['name']];

            $sheet->setCellValue([$column, $row], $packageGroup['name']);
            $sheet->getStyle([1, $row, 26, $row])->applyFromArray($this->getGroupHeaderStyle());
            $row++;

            foreach ($currentGroup as $packageName => $packageRow) {
                $sheet->setCellValue([$column, $row], $packageName);
                $column++;

                foreach ($packageRow as $projectName => $versionCell) {
                    $sheet->setCellValue([$column, $row], $versionCell['value']);

                    $style = $this->getPackageVersionCellStyle($versionCell['value'], $packageName);
                    if (!empty($style)) {
                        $sheet->getStyle([$column, $row])->applyFromArray($style);
                    }

                    if (!empty($versionCell['comment'])) {
                        $sheet->getComment([$column, $row])->getText()->createTextRun($versionCell['comment']);
                    }

                    $column++;
                }

                $column = 1;
                $row++;
            }
        }
    }

    protected function getHeaderStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
            ]
        ];
    }

    protected function getGroupHeaderStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => $this->stylingConfig->getGroupHeaderBackgroundColor(),
                ],
            ],
        ];
    }

    protected function normalizeSheetTitle(string $title): string
    {
        $normalizedTitle = trim($title);
        if ($normalizedTitle === '') {
            $normalizedTitle = 'Packages in projects';
        }

        return substr($normalizedTitle, 0, 31);
    }
}
