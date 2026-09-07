<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Writer;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\Data\StylingConfigInterface;
use EvilStudio\ComposerParser\Api\WriterInterface;
use EvilStudio\ComposerParser\Service\Report\ReportFactory;
use EvilStudio\ComposerParser\Service\Writer\Support\HandlesLocalOutputPath;
use EvilStudio\ComposerParser\Service\Writer\Support\OrdersGroupsByConfig;
use EvilStudio\ComposerParser\Service\Writer\Support\ResolvesSecurityCellStyle;
use EvilStudio\ComposerParser\Service\Writer\Support\ResolvesVersionCellStyle;

class Html implements WriterInterface
{
    use HandlesLocalOutputPath;
    use OrdersGroupsByConfig;
    use ResolvesVersionCellStyle;
    use ResolvesSecurityCellStyle;

    protected const string FILE_EXTENSION = '.html';
    protected const int PACKAGE_COLUMN_WIDTH_PX = 300;
    protected const int PROJECT_COLUMN_WIDTH_PX = 85;

    public function __construct(
        protected string $fileName,
        protected string $fileDirectory,
        protected PackageConfigInterface $packageConfig,
        protected StylingConfigInterface $stylingConfig,
        protected ReportFactory $reportFactory
    ) {
    }

    public function execute(ParsedDataInterface $parsedData): void
    {
        $report = $this->reportFactory->build($parsedData);
        $this->prepareSecurityFindings($report->getSecurityFindings());

        $projects = $report->getProjectNames();
        $groups = $this->getOrderedGroups($report->getGroups());
        $generatedAt = date('Y-m-d H:i');
        $columnsCount = count($projects) + 1;

        $html = [];
        $html[] = '<!DOCTYPE html>';
        $html[] = '<html lang="en">';
        $html[] = '<head>';
        $html[] = '<meta charset="utf-8">';
        $html[] = '<meta name="viewport" content="width=device-width, initial-scale=1">';
        $html[] = '<title>Composer Parser Report</title>';
        $html[] = '<style>';
        $html[] = 'body { font-family: Arial, sans-serif; margin: 20px; color: #111; }';
        $html[] = 'h1 { margin-bottom: 8px; }';
        $html[] = 'p.meta { color: #555; margin-top: 0; }';
        $html[] = 'table { border-collapse: collapse; width: 100%; table-layout: fixed; }';
        $html[] = 'thead th { position: sticky; top: 0; z-index: 2; background: #f7f7f7; text-align: left; }';
        $html[] = 'th, td { border: 1px solid #ddd; padding: 8px; font-size: 13px; vertical-align: top; }';
        $html[] = sprintf('th.col-package, td.col-package { width: %dpx; }', self::PACKAGE_COLUMN_WIDTH_PX);
        $html[] = sprintf('th.col-project, td.col-project { width: %dpx; }', self::PROJECT_COLUMN_WIDTH_PX);
        $html[] = 'th.col-package { position: sticky; left: 0; z-index: 3; background: #f7f7f7; }';
        $html[] = 'td.col-package { position: sticky; left: 0; z-index: 1; background: #fff; }';
        $html[] = 'td.col-package { font-weight: 700; }';
        $html[] = '.group-row td { font-weight: 700; color: #111; }';
        $html[] = '.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }';
        $html[] = '.comment-marker { display: inline-block; margin-top: 6px; font-size: 11px; border: 1px solid #bbb; border-radius: 3px; padding: 1px 4px; color: #444; background: #fafafa; cursor: help; }';
        $html[] = '</style>';
        $html[] = '</head>';
        $html[] = '<body>';
        $html[] = '<h1>Composer Parser Report</h1>';
        $html[] = '<p class="meta">Generated at: ' . $this->escape($generatedAt) . '</p>';
        $html[] = '<table>';
        $summaryAttribute = trim($report->getSecuritySummary()) !== '' ? ' title="' . $this->escape($report->getSecuritySummary()) . '"' : '';
        $html[] = '<thead><tr><th class="col-package"' . $summaryAttribute . '>Package</th>';
        foreach ($projects as $projectName) {
            $projectStyleAttribute = $this->isFlaggedProject((string) $projectName) ? $this->buildStyleAttribute($this->getSecurityCellStyle()) : '';
            $html[] = '<th class="col-project"' . $projectStyleAttribute . '>' . $this->escape((string) $projectName) . '</th>';
        }
        $html[] = '</tr></thead><tbody>';

        foreach ($groups as $groupName => $packages) {
            $groupStyle = ' style="background:#' . $this->escape($this->stylingConfig->getGroupHeaderBackgroundColor()) . ';"';
            $html[] = '<tr class="group-row"><td colspan="' . $columnsCount . '"' . $groupStyle . '>' . $this->escape((string) $groupName) . '</td></tr>';

            foreach ($packages as $packageName => $packageRow) {
                $html[] = '<tr>';
                $packageStyleAttribute = $this->isFlaggedPackage((string) $packageName) ? $this->buildStyleAttribute($this->getSecurityCellStyle()) : '';
                $html[] = '<td class="col-package"' . $packageStyleAttribute . ' title="' . $this->escape((string) $packageName) . '">'
                    . '<div class="truncate">' . $this->escape((string) $packageName) . '</div></td>';

                foreach ($projects as $projectName) {
                    $cell = $packageRow[$projectName] ?? ['value' => '', 'comment' => ''];
                    $valueRaw = (string) ($cell['value'] ?? '');
                    $value = $this->escape($valueRaw);
                    $commentRaw = trim((string) ($cell['comment'] ?? ''));

                    $style = $this->isFlaggedCell($projectName, (string) $packageName) ? $this->getSecurityCellStyle() : $this->getPackageVersionCellStyle($valueRaw, (string) $packageName);
                    $styleAttribute = $this->buildStyleAttribute($style);

                    $cellHtml = '<div class="truncate" title="' . $value . '">' . $value . '</div>';
                    if ($commentRaw !== '') {
                        $commentTooltip = $this->escape(str_replace("\n", ' | ', $commentRaw));
                        $cellHtml .= '<span class="comment-marker" title="' . $commentTooltip . '">COMMENT</span>';
                    }

                    $html[] = '<td class="col-project"' . $styleAttribute . '>' . $cellHtml . '</td>';
                }

                $html[] = '</tr>';
            }
        }

        $html[] = '</tbody></table>';

        $html[] = '</body>';
        $html[] = '</html>';

        $this->writeFile(implode("\n", $html) . "\n");
    }

    protected function writeFile(string $content): void
    {
        $this->writeLocalFile($content);
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function buildStyleAttribute(array $style): string
    {
        $css = [];
        if (isset($style['font']['color']['rgb'])) {
            $css[] = 'color:#' . $style['font']['color']['rgb'];
        }

        if (isset($style['fill']['startColor']['rgb'])) {
            $css[] = 'background:#' . $style['fill']['startColor']['rgb'];
        }

        if ($css === []) {
            return '';
        }

        return ' style="' . $this->escape(implode(';', $css)) . '"';
    }
}
