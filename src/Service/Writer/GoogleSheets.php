<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Writer;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\Data\StylingConfigInterface;
use EvilStudio\ComposerParser\Api\GoogleSheetsClientInterface;
use EvilStudio\ComposerParser\Api\WriterInterface;
use EvilStudio\ComposerParser\Service\Report\ReportFactory;
use EvilStudio\ComposerParser\Service\Writer\Support\OrdersGroupsByConfig;
use EvilStudio\ComposerParser\Service\Writer\Support\ResolvesVersionCellStyle;

class GoogleSheets implements WriterInterface
{
    use OrdersGroupsByConfig;
    use ResolvesVersionCellStyle;

    public function __construct(
        protected string $sheetName,
        protected string $spreadsheetId,
        protected string $serviceAccountJsonPath,
        protected PackageConfigInterface $packageConfig,
        protected StylingConfigInterface $stylingConfig,
        protected ReportFactory $reportFactory,
        protected GoogleSheetsClientInterface $googleSheetsClient
    ) {}

    public function execute(ParsedDataInterface $parsedData): void
    {
        $report = $this->reportFactory->build($parsedData);
        $projects = $report->getProjectNames();
        $groups = $this->getOrderedGroups($report->getGroups());

        $values = [];
        $groupRows = [];
        $cellStyles = [];
        $notes = [];

        $values[] = array_merge([sprintf('Last update: %s', date('Y-m-d H:i'))], $projects);
        $row = 2;

        foreach ($groups as $groupName => $packages) {
            $values[] = array_merge([$groupName], array_fill(0, count($projects), ''));
            $groupRows[] = $row;
            $row++;

            foreach ($packages as $packageName => $packageRow) {
                $rowValues = [$packageName];
                $column = 2;

                foreach ($projects as $projectName) {
                    $cell = $packageRow[$projectName] ?? ['value' => '', 'comment' => ''];
                    $value = (string) ($cell['value'] ?? '');
                    $comment = trim((string) ($cell['comment'] ?? ''));

                    $rowValues[] = $value;

                    $style = $this->getPackageVersionCellStyle($value, (string) $packageName);
                    if (isset($style['font']['color']['rgb']) || isset($style['fill']['startColor']['rgb'])) {
                        $cellStyles[] = [
                            'row' => $row,
                            'column' => $column,
                            'fontColor' => $style['font']['color']['rgb'] ?? null,
                            'backgroundColor' => $style['fill']['startColor']['rgb'] ?? null,
                        ];
                    }

                    if ($comment !== '') {
                        $notes[] = [
                            'row' => $row,
                            'column' => $column,
                            'note' => $comment,
                        ];
                    }

                    $column++;
                }

                $values[] = $rowValues;
                $row++;
            }
        }

        $this->googleSheetsClient->write(
            $this->serviceAccountJsonPath,
            $this->spreadsheetId,
            $this->sheetName,
            $values,
            $groupRows,
            $this->stylingConfig->getGroupHeaderBackgroundColor(),
            $cellStyles,
            $notes
        );
    }
}
