<?php

namespace EvilStudio\ComposerParser\Api\Data;

interface StylingConfigInterface
{
    public function getGroupHeaderBackgroundColor(): string;

    public function getCellStyleMapping(): array;
}