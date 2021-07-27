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
    public function execute(ParsedDataInterface $parsedData): void
    {
        $this->prepareSpreadsheet();

        $this->prepareHeader($parsedData->getprojectNames());
        $this->prepareData($parsedData->getProjectsData());

        $this->writeSpreadsheet();
    }

    protected function prepareSpreadsheet(): void
    {
        $this->spreadsheet = new Spreadsheet();

        $this->spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
        $this->spreadsheet->getDefaultStyle()->getFont()->setSize(10);
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
     * @param array $projectNames
     */
    protected function prepareHeader(array $projectNames): void
    {
        $sheet = $this->spreadsheet->getActiveSheet();

        $currentDate = date('Y-m-d H:i');
        $sheet->getColumnDimensionByColumn(1)->setAutoSize(true);
        $sheet->setCellValueByColumnAndRow(1, 1, sprintf('Last update: %s', $currentDate));

        $column = 2;
        foreach ($projectNames as $projectName) {
            $sheet->setCellValueByColumnAndRow($column, 1, $projectName);
            $sheet->getStyleByColumnAndRow($column, 1)->applyFromArray($this->getHeaderStyle());
            $sheet->getColumnDimensionByColumn($column)->setWidth(10);
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

        $column = 1;
        $row = 2;
        foreach ($packageGroups as $packageGroup) {
            if (!array_key_exists($packageGroup['name'], $parsedComposerJson)) {
                continue;
            }

            $currentGroup = $parsedComposerJson[$packageGroup['name']];

            $sheet->setCellValueByColumnAndRow($column, $row, $packageGroup['name']);
            $sheet->getStyleByColumnAndRow(1, $row, 26, $row)->applyFromArray($this->getGroupHeaderStyle());
            $row++;

            foreach ($currentGroup as $packageName => $packageRow) {
                $sheet->setCellValueByColumnAndRow($column, $row, $packageName);
                $column++;

                foreach ($packageRow as $projectName => $versionCell) {
                    $sheet->setCellValueByColumnAndRow($column, $row, $versionCell['value']);

                    $style = $this->getPackageVersionCellStyle($versionCell['value']);
                    if (!empty($style)) {
                        $sheet->getStyleByColumnAndRow($column, $row)->applyFromArray($style);
                    }

                    if (!empty($versionCell['comment'])) {
                        $sheet->getCommentByColumnAndRow($column, $row)->getText()->createTextRun($versionCell['comment']);
                    }

                    $column++;
                }

                $column = 1;
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