<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Writer\Support;

trait ResolvesSecurityCellStyle
{
    protected array $flaggedProjects = [];
    protected array $flaggedPackages = [];
    protected array $flaggedCells = [];

    protected function prepareSecurityFindings(array $securityFindings): void
    {
        $this->flaggedProjects = [];
        $this->flaggedPackages = [];
        $this->flaggedCells = [];

        foreach ($securityFindings as $finding) {
            $this->flaggedProjects[$finding->getProjectName()] = true;
            $this->flaggedPackages[$finding->getPackageName()] = true;
            $this->flaggedCells[$finding->getProjectName()][$finding->getPackageName()] = true;
        }
    }

    protected function hasSecurityFindings(): bool
    {
        return $this->flaggedCells !== [];
    }

    protected function isFlaggedProject(string $projectName): bool
    {
        return isset($this->flaggedProjects[$projectName]);
    }

    protected function isFlaggedPackage(string $packageName): bool
    {
        return isset($this->flaggedPackages[$packageName]);
    }

    protected function isFlaggedCell(string $projectName, string $packageName): bool
    {
        return isset($this->flaggedCells[$projectName][$packageName]);
    }

    protected function getSecurityCellStyle(): array
    {
        return $this->stylingConfig->getSecurityHighlightStyle();
    }
}
