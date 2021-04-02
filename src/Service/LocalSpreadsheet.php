<?php

namespace EvilStudio\ComposerParser\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LocalSpreadsheet
{

    /**
     * @var array
     */
    protected $spreadsheetConfig;

    /**
     * @var Spreadsheet
     */
    protected $spreadsheet;

    /**
     * @param $spreadsheetConfig
     */
    public function __construct($spreadsheetConfig)
    {
        $this->spreadsheetConfig = $spreadsheetConfig;
    }

    /**
     * @param $parsedData
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function write($parsedData): void
    {
        $this->prepareSpreadsheet();

        $this->writeHeader($parsedData['projectCodes']);
        $this->writeData($parsedData['projectData']);

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
        $fileDirectory = $this->spreadsheetConfig['local']['file_directory'];
        if (!file_exists($fileDirectory)) {
            mkdir($fileDirectory, 0777, true);
        }

        $fileName = $this->spreadsheetConfig['local']['file_name'];
        $fileName = str_replace("{date}", date('Y-m-d'), $fileName);

        $filePath = $fileDirectory . DIRECTORY_SEPARATOR . $fileName;

        $writer = new Xlsx($this->spreadsheet);
        $writer->save($filePath);
    }

    /**
     * @param array $projectCodes
     */
    protected function writeHeader(array $projectCodes): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();

        $currentDate = date('Y-m-d H:i');
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->setCellValue('A1', sprintf('Last update: %s', $currentDate));

        $char = 'B';
        foreach ($projectCodes as $projectCode => $cell) {
//            $sheet->getColumnDimension($char)->setAutoSize(true);
            $sheet->setCellValue(sprintf('%s1', $char), $projectCode);
            $char++;
        }
    }

    /**
     * @param array $parsedComposerJson
     */
    protected function writeData(array $parsedComposerJson): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();

        $styleArray = [
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => '999999',
                ],
            ],
        ];
        $col = 'A';
        $row = 2;

        $packagesGroups = $this->spreadsheetConfig['packages_groups'];
        usort($packagesGroups, function ($a, $b) {
            return $a['order'] > $b['order'] ? 1 : -1;
        });

        foreach ($packagesGroups as $packagesGroup) {
            $currentGroup = $parsedComposerJson[$packagesGroup['name']];

            $sheet->setCellValue(sprintf('%s%s', $col, $row), $packagesGroup['name']);
            $sheet->getStyle(sprintf('A%s:Z%s', $row, $row))->applyFromArray($styleArray);
            $row++;

            foreach ($currentGroup as $indexGroup => $group) {
                $sheet->setCellValue(sprintf('%s%s', $col, $row), $indexGroup);
                $col++;

                foreach ($group as $indexItem => $item) {
                    $sheet->setCellValue(sprintf('%s%s', $col, $row), $item);
                    $col++;
                }

                $col = 'A';
                $row++;
            }
        }
    }
}