<?php

namespace EvilStudio\ComposerParser\Api\Data;

interface StylingConfigInterface
{
    /**
     * @return string
     */
    public function getGroupHeaderBackgroundColor(): string;

    /**
     * @return array
     */
    public function getCellColorMapping(): array;
}