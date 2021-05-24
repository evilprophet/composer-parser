<?php

namespace EvilStudio\ComposerParser\Service\Writer;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\WriterInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxSpreadsheet;

class Xlsx implements WriterInterface
{
    const FILE_EXTENSION = '.xlsx';

    /**
     * @var string
     */
    protected $fileName;

    /**
     * @var string
     */
    protected $fileDirectory;

    /**
     * @var Spreadsheet
     */
    protected $spreadsheet;

    /**
     * @var PackageConfigInterface
     */
    protected $packageConfig;

    /**
     * Xlsx constructor.
     * @param string $fileName
     * @param string $fileDirectory
     * @param PackageConfigInterface $packageConfig
     */
    public function __construct(string $fileName, string $fileDirectory, PackageConfigInterface $packageConfig)
    {
        $this->fileName = $fileName;
        $this->fileDirectory = $fileDirectory;
        $this->packageConfig = $packageConfig;
    }

    /**
     * @param ParsedDataInterface $parsedData
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function write(ParsedDataInterface $parsedData): void
    {
        $this->prepareSpreadsheet();

        $this->prepareHeader($parsedData->getProjectCodes());
        $this->prepareData($parsedData->getProjectsData());

        $this->writeSpreadsheet();
    }

    protected function prepareSpreadsheet(): void
    {
        $this->spreadsheet = new Spreadsheet();
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    protected function writeSpreadsheet(): void
    {
        $filePath = $this->getFilePath();

        $xlsxWriter = new XlsxSpreadsheet($this->spreadsheet);
        $xlsxWriter->save($filePath);
    }

    /**
     * @param array $projectCodes
     */
    protected function prepareHeader(array $projectCodes): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();

        $currentDate = date('Y-m-d H:i');
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->setCellValue('A1', sprintf('Last update: %s', $currentDate));

        $column = 'B';
        foreach ($projectCodes as $projectCode) {
            $sheet->setCellValue(sprintf('%s1', $column), $projectCode);
            $sheet->getStyle(sprintf('%s1', $column))->applyFromArray($this->getHeaderStyle());
            $column++;
        }
    }

    /**
     * @param array $parsedComposerJson
     */
    protected function prepareData(array $parsedComposerJson): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        $packageGroups = $this->packageConfig->getPackageGroupsForWriter();

        $column = 'A';
        $row = 2;
        foreach ($packageGroups as $packageGroup) {
            $currentGroup = $parsedComposerJson[$packageGroup['name']];

            $sheet->setCellValue(sprintf('%s%s', $column, $row), $packageGroup['name']);
            $sheet->getStyle(sprintf('A%s:Z%s', $row, $row))->applyFromArray($this->getGroupHeaderStyle());
            $row++;

            foreach ($currentGroup as $packageName => $packageRow) {
                $sheet->setCellValue(sprintf('%s%s', $column, $row), $packageName);
                $column++;

                foreach ($packageRow as $projectName => $versionCell) {
                    $cellCoordinate = sprintf('%s%s', $column, $row);
                    $sheet->setCellValue($cellCoordinate, $versionCell['value']);

                    $style = $this->getPackageVersionCellStyle($versionCell['value']);
                    if (!empty($style)) {
                        $sheet->getStyle($cellCoordinate)->applyFromArray($style);
                    }

                    if (!empty($versionCell['comment'])) {
                        $sheet->getComment($cellCoordinate)->getText()->createTextRun($versionCell['comment']);
                    }

                    $column++;
                }

                $column = 'A';
                $row++;
            }
        }
    }

    /**
     * @return string
     */
    protected function getFileDirectory(): string
    {
        if (!file_exists($this->fileDirectory)) {
            mkdir($this->fileDirectory, 0777, true);
        }

        return $this->fileDirectory;
    }

    /**
     * @return string
     */
    protected function getFilePath(): string
    {
        $fileName = str_replace("{date}", date('Y-m-d'), $this->fileName);

        return $this->getFileDirectory() . DIRECTORY_SEPARATOR . $fileName . self::FILE_EXTENSION;
    }

    /**
     * @return array
     */
    protected function getHeaderStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
            ]
        ];
    }

    /**
     * @return array
     */
    protected function getGroupHeaderStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => '999999',
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    protected function getPackageVersionCellStyle($versionCell): array
    {
        if (!preg_match('/(dev-.*|.*-dev)/', $versionCell)) {
            return [];
        }

        return [
            'font' => [
                'color' => [
                    'argb' => 'FF8000',
                ],
            ],
        ];
    }
}