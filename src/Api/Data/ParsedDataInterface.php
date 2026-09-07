<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Api\Data;

interface ParsedDataInterface
{
    public function getGroups(): array;

    public function getProjectNames(): array;

    public function getSecurityFindings(): array;

    public function getSecuritySummary(): string;
}
