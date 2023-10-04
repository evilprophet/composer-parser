<?php

namespace EvilStudio\ComposerParser\Model;

use EvilStudio\ComposerParser\Api\Data\StylingConfigInterface;

class StylingConfig implements StylingConfigInterface
{
    protected string $groupHeaderBackgroundColor;

    protected array $cellStyleMapping;

    public function __construct(array $stylingConfigData)
    {
        $this->groupHeaderBackgroundColor = (string)$stylingConfigData['groupHeaderBackgroundColor'] ?? '';
        $this->cellStyleMapping = (array)$stylingConfigData['cellStyleMapping'] ?? [];
    }

    public function getGroupHeaderBackgroundColor(): string
    {
        return $this->groupHeaderBackgroundColor;
    }

    public function getCellStyleMapping(): array
    {
        return $this->cellStyleMapping;
    }
}