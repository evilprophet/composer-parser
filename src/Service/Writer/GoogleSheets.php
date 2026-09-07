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
use EvilStudio\ComposerParser\Service\Writer\Support\ResolvesSecurityCellStyle;
use EvilStudio\ComposerParser\Service\Writer\Support\ResolvesVersionCellStyle;

class GoogleSheets implements WriterInterface
{
    use OrdersGroupsByConfig;
    use ResolvesVersionCellStyle;
    use ResolvesSecurityCellStyle;

    protected const int PACKAGE_NAME_COLUMN = 1;
    protected const int SUMMARY_NOTE_ROW = 1;

    public function __construct(
        protected string $sheetName,
        protected string $spreadsheetId,
        protected string $serviceAccountJsonPath,
        protected PackageConfigInterface $packageConfig,
        protected StylingConfigInterface $stylingConfig,
        protected ReportFactory $reportFactory,
        protected GoogleSheetsClientInterface $googleSheetsClient
    ) {
    }

    public function execute(ParsedDataInterface $parsedData): void
    {
        $report = $this->reportFactory->build($parsedData);
        $projects = $report->getProjectNames();
        $groups = $this->getOrderedGroups($report->getGroups());
        $this->prepareSecurityFindings($report->getSecurityFindings());

        $values = [];
        $groupRows = [];
        $cellStyles = [];
        $notes = [];

        $values[] = array_merge([sprintf('Last update: %s', date('Y-m-d H:i'))], $projects);
        $this->addHeaderSecurityStyles($projects, $cellStyles);
        $this->addSummaryNote($report->getSecuritySummary(), $notes);
        $row = 2;

        foreach ($groups as $groupName => $packages) {
            $values[] = array_merge([$groupName], array_fill(0, count($projects), ''));
            $groupRows[] = $row;
            $row++;

            foreach ($packages as $packageName => $packageRow) {
                $rowValues = [$packageName];
                $rowCellStyles = [];
                $rowNotesByColumn = [];
                $column = 2;

                if ($this->isFlaggedPackage((string) $packageName)) {
                    $rowCellStyles[self::PACKAGE_NAME_COLUMN] = $this->toGoogleSheetsStyle($this->getSecurityCellStyle());
                }

                foreach ($projects as $projectName) {
                    $cell = $packageRow[$projectName] ?? ['value' => '', 'comment' => ''];
                    $value = (string) ($cell['value'] ?? '');
                    $comment = trim((string) ($cell['comment'] ?? ''));

                    $rowValues[] = $value;

                    $style = $this->isFlaggedCell($projectName, (string) $packageName) ? $this->getSecurityCellStyle() : $this->getPackageVersionCellStyle($value, (string) $packageName);
                    if (isset($style['font']['color']['rgb']) || isset($style['fill']['startColor']['rgb'])) {
                        $rowCellStyles[$column] = $this->toGoogleSheetsStyle($style);
                    }

                    if ($comment !== '') {
                        $rowNotesByColumn[$column] = $comment;
                    }

                    $column++;
                }

                $values[] = $rowValues;

                if ($rowCellStyles !== []) {
                    $cellStyles[] = [
                        'row' => $row,
                        'stylesByColumn' => $rowCellStyles,
                    ];
                }

                if ($rowNotesByColumn !== []) {
                    $notes[] = [
                        'row' => $row,
                        'notesByColumn' => $rowNotesByColumn,
                    ];
                }

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

    protected function addHeaderSecurityStyles(array $projects, array &$cellStyles): void
    {
        if (!$this->hasSecurityFindings()) {
            return;
        }

        $securityStyle = $this->toGoogleSheetsStyle($this->getSecurityCellStyle());
        $stylesByColumn = [];
        $column = 2;

        foreach ($projects as $projectName) {
            if ($this->isFlaggedProject((string) $projectName)) {
                $stylesByColumn[$column] = $securityStyle;
            }

            $column++;
        }

        if ($stylesByColumn === []) {
            return;
        }

        $cellStyles[] = [
            'row' => self::SUMMARY_NOTE_ROW,
            'stylesByColumn' => $stylesByColumn,
        ];
    }

    protected function addSummaryNote(string $securitySummary, array &$notes): void
    {
        if (trim($securitySummary) === '') {
            return;
        }

        $notes[] = [
            'row' => self::SUMMARY_NOTE_ROW,
            'notesByColumn' => [self::PACKAGE_NAME_COLUMN => $securitySummary],
        ];
    }

    protected function toGoogleSheetsStyle(array $style): array
    {
        return [
            'fontColor' => $style['font']['color']['rgb'] ?? null,
            'backgroundColor' => $style['fill']['startColor']['rgb'] ?? null,
        ];
    }
}
