<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Api;

interface GoogleSheetsClientInterface
{
    public function write(
        string $serviceAccountJsonPath,
        string $spreadsheetId,
        string $sheetName,
        array $values,
        array $groupRows,
        string $groupHeaderBackgroundColor,
        array $cellStyles,
        array $notes
    ): void;
}
