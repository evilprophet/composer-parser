<?php

namespace EvilStudio\ComposerParser\Model;

use EvilStudio\ComposerParser\Api\Data\StylingConfigInterface;

class StylingConfig implements StylingConfigInterface
{
    /**
     * @var string
     */
    protected $groupHeaderBackgroundColor;

    /**
     * @var array
     */
    protected $cellStyleMapping;

    /**
     * StylingConfig constructor.
     * @param array $stylingConfigData
     */
    public function __construct(array $stylingConfigData)
    {
        $this->groupHeaderBackgroundColor = (string)$stylingConfigData['groupHeaderBackgroundColor'] ?? '';
        $this->cellStyleMapping = (array)$stylingConfigData['cellStyleMapping'] ?? '';
    }

    /**
     * @return string
     */
    public function getGroupHeaderBackgroundColor(): string
    {
        return $this->groupHeaderBackgroundColor;
    }

    /**
     * @return array
     */
    public function getCellStyleMapping(): array
    {
        return $this->cellStyleMapping;
    }
}