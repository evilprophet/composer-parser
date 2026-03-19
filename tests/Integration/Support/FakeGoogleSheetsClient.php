<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Support;

use EvilStudio\ComposerParser\Api\GoogleSheetsClientInterface;

class FakeGoogleSheetsClient implements GoogleSheetsClientInterface
{
    public array $calls = [];

    public function write(
        string $serviceAccountJsonPath,
        string $spreadsheetId,
        string $sheetName,
        array $values,
        array $groupRows,
        string $groupHeaderBackgroundColor,
        array $cellStyles,
        array $notes
    ): void {
        $this->calls[] = [
            'serviceAccountJsonPath' => $serviceAccountJsonPath,
            'spreadsheetId' => $spreadsheetId,
            'sheetName' => $sheetName,
            'values' => $values,
            'groupRows' => $groupRows,
            'groupHeaderBackgroundColor' => $groupHeaderBackgroundColor,
            'cellStyles' => $cellStyles,
            'notes' => $notes,
        ];
    }
}
