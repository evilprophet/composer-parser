<?php

namespace EvilStudio\ComposerParser\Service\Writer;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
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
     * @param array $writerConfig
     */
    public function __construct(string $fileName, string $fileDirectory, PackageConfigInterface $packageConfig)
    {
        $this->fileName = $fileName;
        $this->fileDirectory = $fileDirectory;
        $this->packageConfig = $packageConfig;
    }

    /**
     * @param array $parsedData
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function write(array $parsedData): void
    {
        $this->prepareSpreadsheet();

        $this->prepareHeader($parsedData['projectCodes']);
        $this->prepareData($parsedData['projectData']);

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
        $packagesGroups = $this->packageConfig->getPackageGroupsForWriter();

        $column = 'A';
        $row = 2;
        foreach ($packagesGroups as $packagesGroup) {
            $currentGroup = $parsedComposerJson[$packagesGroup['name']];

            $sheet->setCellValue(sprintf('%s%s', $column, $row), $packagesGroup['name']);
            $sheet->getStyle(sprintf('A%s:Z%s', $row, $row))->applyFromArray($this->getGroupHeaderStyle());
            $row++;

            foreach ($currentGroup as $indexGroup => $group) {
                $sheet->setCellValue(sprintf('%s%s', $column, $row), $indexGroup);
                $column++;

                foreach ($group as $indexItem => $item) {
                    $sheet->setCellValue(sprintf('%s%s', $column, $row), $item);
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
                    'argb' => '999999',
                ],
            ],
        ];
    }
}