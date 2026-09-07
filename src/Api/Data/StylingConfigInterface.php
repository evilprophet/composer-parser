<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Api\Data;

interface StylingConfigInterface
{
    public function getGroupHeaderBackgroundColor(): string;

    public function getCellStyleMapping(): array;

    public function getSecurityHighlightStyle(): array;
}
