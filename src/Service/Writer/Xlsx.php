<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Writer;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\Data\StylingConfigInterface;
use EvilStudio\ComposerParser\Api\WriterInterface;
use EvilStudio\ComposerParser\Model\Report;
use EvilStudio\ComposerParser\Service\Report\ReportFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxSpreadsheet;
use Symfony\Component\Filesystem\Filesystem;

class Xlsx implements WriterInterface
{
    protected const string FILE_EXTENSION = '.xlsx';

    protected Spreadsheet $spreadsheet;

    public function __construct(
        protected string $fileName,
        protected string $fileDirectory,
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

    protected function getFileDirectory(): string
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->fileDirectory, 0777);

        return $this->fileDirectory;
    }

    protected function getFilePath(): string
    {
        $fileName = str_replace("{date}", date('Y-m-d'), $this->fileName);

        return $this->getFileDirectory() . DIRECTORY_SEPARATOR . $fileName . self::FILE_EXTENSION;
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

    protected function getPackageVersionCellStyle(string $versionCell, string $packageName): array
    {
        $styling = [];
        $cellStyleMapping = $this->stylingConfig->getCellStyleMapping();

        foreach ($cellStyleMapping as $cellStyle) {
            if (isset($cellStyle['packageNameRegex']) && !preg_match($cellStyle['packageNameRegex'], $packageName)) {
                continue;
            }

            if (!preg_match($cellStyle['versionRegex'], $versionCell)) {
                continue;
            }

            if (isset($cellStyle['color'])) {
                $styling['font']['color']['rgb'] = $cellStyle['color'];
            }

            if (isset($cellStyle['backgroundColor'])) {
                $styling['fill']['fillType'] = Fill::FILL_SOLID;
                $styling['fill']['startColor']['rgb'] = $cellStyle['backgroundColor'];
            }
        }

        return $styling;
    }
}
