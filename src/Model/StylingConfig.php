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
    protected $cellColorMapping;


    /**
     * StylingConfig constructor.
     * @param array $stylingConfigData
     */
    public function __construct(array $stylingConfigData)
    {
        $this->groupHeaderBackgroundColor = (string)$stylingConfigData['groupHeaderBackgroundColor'] ?? '';
        $this->cellColorMapping = (array)$stylingConfigData['cellColorMapping'] ?? '';
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
    public function getCellColorMapping(): array
    {
        return $this->cellColorMapping;
    }
}