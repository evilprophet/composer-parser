<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Api\Data;

interface ParsedDataInterface
{
    public function getProjectsData(): array;

    public function getProjectNames(): array;
}
