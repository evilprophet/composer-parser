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
use EvilStudio\ComposerParser\Service\Writer\Support\OrdersGroupsByConfig;
use EvilStudio\ComposerParser\Service\Writer\Support\ResolvesSecurityCellStyle;
use EvilStudio\ComposerParser\Service\Writer\Support\ResolvesVersionCellStyle;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxSpreadsheet;

class Xlsx implements WriterInterface
{
    use HandlesLocalOutputPath;
    use OrdersGroupsByConfig;
    use ResolvesVersionCellStyle;
    use ResolvesSecurityCellStyle;

    protected const string FILE_EXTENSION = '.xlsx';
    protected const int PROJECT_COLUMN_WIDTH = 10;

    protected Spreadsheet $spreadsheet;

    public function __construct(
        protected string $fileName,
        protected string $fileDirectory,
        protected string $sheetName,
        protected PackageConfigInterface $packageConfig,
        protected StylingConfigInterface $stylingConfig,
        protected ReportFactory $reportFactory
    ) {
    }

    public function execute(ParsedDataInterface $parsedData): void
    {
        $report = $this->reportFactory->build($parsedData);
        $this->prepareSecurityFindings($report->getSecurityFindings());

        $this->prepareSpreadsheet();

        $this->prepareHeader($report->getProjectNames());
        $this->prepareSummaryComment($report->getSecuritySummary());
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
            if ($this->isFlaggedProject((string) $projectName)) {
                $sheet->getStyle([$column, 1])->applyFromArray($this->getSecurityCellStyle());
            }
            $sheet->getColumnDimensionByColumn($column)->setWidth(self::PROJECT_COLUMN_WIDTH);
            $column++;
        }
    }

    protected function prepareData(Report $report): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        $orderedGroups = $this->getOrderedGroups($report->getGroups());
        $projectNames = $report->getProjectNames();

        $column = 1;
        $row = 2;
        foreach ($orderedGroups as $groupName => $currentGroup) {
            $sheet->setCellValue([$column, $row], $groupName);
            $sheet->getStyle([1, $row, count($projectNames) + 1, $row])->applyFromArray($this->getGroupHeaderStyle());
            $row++;

            foreach ($currentGroup as $packageName => $packageRow) {
                $sheet->setCellValueExplicit([$column, $row], $packageName, DataType::TYPE_STRING);
                if ($this->isFlaggedPackage((string) $packageName)) {
                    $sheet->getStyle([$column, $row])->applyFromArray($this->getSecurityCellStyle());
                }
                $column++;

                foreach ($projectNames as $projectName) {
                    $versionCell = $packageRow[$projectName] ?? ['value' => '', 'comment' => ''];
                    $sheet->setCellValueExplicit([$column, $row], $versionCell['value'], DataType::TYPE_STRING);

                    $style = $this->isFlaggedCell($projectName, (string) $packageName) ? $this->getSecurityCellStyle() : $this->getPackageVersionCellStyle($versionCell['value'], $packageName);
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

    protected function prepareSummaryComment(string $securitySummary): void
    {
        if (trim($securitySummary) === '') {
            return;
        }

        $this->spreadsheet->getActiveSheet()->getComment([1, 1])->getText()->createTextRun($securitySummary);
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
        return substr(trim($title), 0, 31);
    }
}
