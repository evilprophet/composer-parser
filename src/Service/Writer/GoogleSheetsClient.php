<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Writer;

use EvilStudio\ComposerParser\Api\GoogleSheetsClientInterface;

class GoogleSheetsClient implements GoogleSheetsClientInterface
{
    protected const int PROJECT_COLUMN_PIXEL_SIZE = 95;

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
        if (!class_exists(\Google\Client::class) || !class_exists(\Google\Service\Sheets::class)) {
            throw new \RuntimeException('Google API client is not installed. Run: composer require google/apiclient');
        }

        if (!is_file($serviceAccountJsonPath)) {
            throw new \RuntimeException(sprintf('Google service account json file not found: %s', $serviceAccountJsonPath));
        }

        $client = new \Google\Client();
        $client->setApplicationName('Composer Parser');
        $client->setAuthConfig($serviceAccountJsonPath);
        $client->setScopes([\Google\Service\Sheets::SPREADSHEETS]);

        $service = new \Google\Service\Sheets($client);
        $sheetMetadata = $this->getOrCreateSheetMetadata($service, $spreadsheetId, $sheetName);
        $sheetId = $sheetMetadata['sheetId'];
        $rowCount = $sheetMetadata['rowCount'];
        $columnCount = $sheetMetadata['columnCount'];

        $clearRange = sprintf(
            "'%s'!A1:%s%d",
            $sheetName,
            $this->columnIndexToLetters($columnCount),
            $rowCount
        );
        $service->spreadsheets_values->clear($spreadsheetId, $clearRange, new \Google\Service\Sheets\ClearValuesRequest());
        $this->clearSheetFormattingAndNotes($service, $spreadsheetId, $sheetId, $rowCount, $columnCount);

        $range = sprintf("'%s'!A1", $sheetName);
        $service->spreadsheets_values->update(
            $spreadsheetId,
            $range,
            new \Google\Service\Sheets\ValueRange(['values' => $values]),
            ['valueInputOption' => 'RAW']
        );

        $this->applyFormatting($service, $spreadsheetId, $sheetId, $values, $groupRows, $groupHeaderBackgroundColor, $cellStyles, $notes);
    }

    protected function getOrCreateSheetMetadata(\Google\Service\Sheets $service, string $spreadsheetId, string $sheetName): array
    {
        $spreadsheet = $service->spreadsheets->get($spreadsheetId);
        $sheetMetadata = $this->findSheetMetadata($spreadsheet, $sheetName);
        if ($sheetMetadata !== null) {
            return $sheetMetadata;
        }

        $service->spreadsheets->batchUpdate(
            $spreadsheetId,
            new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                'requests' => [
                    [
                        'addSheet' => [
                            'properties' => [
                                'title' => $sheetName,
                            ],
                        ],
                    ],
                ],
            ])
        );

        $updatedSpreadsheet = $service->spreadsheets->get($spreadsheetId);
        $sheetMetadata = $this->findSheetMetadata($updatedSpreadsheet, $sheetName);
        if ($sheetMetadata !== null) {
            return $sheetMetadata;
        }

        throw new \RuntimeException(sprintf('Failed to create/find sheet: %s', $sheetName));
    }

    protected function findSheetMetadata(\Google\Service\Sheets\Spreadsheet $spreadsheet, string $sheetName): ?array
    {
        foreach ($spreadsheet->getSheets() as $sheet) {
            $properties = $sheet->getProperties();
            if ($properties->getTitle() !== $sheetName) {
                continue;
            }

            $gridProperties = $properties->getGridProperties();

            return [
                'sheetId' => (int) $properties->getSheetId(),
                'rowCount' => max(1, (int) ($gridProperties->getRowCount() ?? 1)),
                'columnCount' => max(1, (int) ($gridProperties->getColumnCount() ?? 1)),
            ];
        }

        return null;
    }

    protected function applyFormatting(
        \Google\Service\Sheets $service,
        string $spreadsheetId,
        int $sheetId,
        array $values,
        array $groupRows,
        string $groupHeaderBackgroundColor,
        array $cellStyles,
        array $notes
    ): void {
        $columnCount = count($values[0] ?? []);
        if ($columnCount === 0) {
            return;
        }

        $requests = [];

        $requests[] = [
            'updateSheetProperties' => [
                'properties' => [
                    'sheetId' => $sheetId,
                    'gridProperties' => [
                        'frozenRowCount' => 1,
                        'frozenColumnCount' => 1,
                    ],
                ],
                'fields' => 'gridProperties.frozenRowCount,gridProperties.frozenColumnCount',
            ],
        ];

        $requests[] = [
            'autoResizeDimensions' => [
                'dimensions' => [
                    'sheetId' => $sheetId,
                    'dimension' => 'COLUMNS',
                    'startIndex' => 0,
                    'endIndex' => 1,
                ],
            ],
        ];

        if ($columnCount > 1) {
            $requests[] = [
                'updateDimensionProperties' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 1,
                        'endIndex' => $columnCount,
                    ],
                    'properties' => [
                        'pixelSize' => self::PROJECT_COLUMN_PIXEL_SIZE,
                    ],
                    'fields' => 'pixelSize',
                ],
            ];
        }

        $requests[] = [
            'repeatCell' => [
                'range' => [
                    'sheetId' => $sheetId,
                    'startRowIndex' => 0,
                    'endRowIndex' => 1,
                    'startColumnIndex' => 1,
                    'endColumnIndex' => $columnCount,
                ],
                'cell' => [
                    'userEnteredFormat' => [
                        'textFormat' => [
                            'bold' => true,
                        ],
                        'backgroundColor' => $this->hexToColor('F7F7F7'),
                    ],
                ],
                'fields' => 'userEnteredFormat(textFormat,backgroundColor)',
            ],
        ];

        $requests[] = [
            'repeatCell' => [
                'range' => [
                    'sheetId' => $sheetId,
                    'startRowIndex' => 0,
                    'endRowIndex' => count($values),
                    'startColumnIndex' => 0,
                    'endColumnIndex' => $columnCount,
                ],
                'cell' => [
                    'userEnteredFormat' => [
                        'wrapStrategy' => 'CLIP',
                    ],
                ],
                'fields' => 'userEnteredFormat.wrapStrategy',
            ],
        ];

        foreach ($groupRows as $groupRow) {
            $requests[] = [
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => $groupRow - 1,
                        'endRowIndex' => $groupRow,
                        'startColumnIndex' => 0,
                        'endColumnIndex' => $columnCount,
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'textFormat' => [
                                'bold' => true,
                            ],
                            'backgroundColor' => $this->hexToColor($groupHeaderBackgroundColor),
                        ],
                    ],
                    'fields' => 'userEnteredFormat(textFormat.bold,backgroundColor)',
                ],
            ];
        }

        foreach ($cellStyles as $cellStyle) {
            $cellFormat = [];
            $fields = [];

            if (isset($cellStyle['fontColor'])) {
                $cellFormat['textFormat']['foregroundColor'] = $this->hexToColor($cellStyle['fontColor']);
                $fields[] = 'userEnteredFormat.textFormat.foregroundColor';
            }

            if (isset($cellStyle['backgroundColor'])) {
                $cellFormat['backgroundColor'] = $this->hexToColor($cellStyle['backgroundColor']);
                $fields[] = 'userEnteredFormat.backgroundColor';
            }

            if ($fields === []) {
                continue;
            }

            $requests[] = [
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => $cellStyle['row'] - 1,
                        'endRowIndex' => $cellStyle['row'],
                        'startColumnIndex' => $cellStyle['column'] - 1,
                        'endColumnIndex' => $cellStyle['column'],
                    ],
                    'cell' => [
                        'userEnteredFormat' => $cellFormat,
                    ],
                    'fields' => implode(',', $fields),
                ],
            ];
        }

        foreach ($notes as $note) {
            $requests[] = [
                'updateCells' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => $note['row'] - 1,
                        'endRowIndex' => $note['row'],
                        'startColumnIndex' => $note['column'] - 1,
                        'endColumnIndex' => $note['column'],
                    ],
                    'rows' => [
                        [
                            'values' => [
                                [
                                    'note' => $note['note'],
                                ],
                            ],
                        ],
                    ],
                    'fields' => 'note',
                ],
            ];
        }

        if ($requests !== []) {
            $service->spreadsheets->batchUpdate(
                $spreadsheetId,
                new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest(['requests' => $requests])
            );
        }
    }

    protected function clearSheetFormattingAndNotes(
        \Google\Service\Sheets $service,
        string $spreadsheetId,
        int $sheetId,
        int $rowCount,
        int $columnCount
    ): void {
        $fullSheetRange = [
            'sheetId' => $sheetId,
            'startRowIndex' => 0,
            'endRowIndex' => $rowCount,
            'startColumnIndex' => 0,
            'endColumnIndex' => $columnCount,
        ];

        $requests = [
            [
                'repeatCell' => [
                    'range' => $fullSheetRange,
                    'cell' => [
                        'userEnteredFormat' => [],
                    ],
                    'fields' => 'userEnteredFormat',
                ],
            ],
            [
                'repeatCell' => [
                    'range' => $fullSheetRange,
                    'cell' => [
                        'note' => '',
                    ],
                    'fields' => 'note',
                ],
            ],
        ];

        $service->spreadsheets->batchUpdate(
            $spreadsheetId,
            new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest(['requests' => $requests])
        );
    }

    protected function hexToColor(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return ['red' => 0.0, 'green' => 0.0, 'blue' => 0.0];
        }

        return [
            'red' => hexdec(substr($hex, 0, 2)) / 255,
            'green' => hexdec(substr($hex, 2, 2)) / 255,
            'blue' => hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    protected function columnIndexToLetters(int $columnIndex): string
    {
        $columnIndex = max(1, $columnIndex);
        $letters = '';

        while ($columnIndex > 0) {
            $modulo = ($columnIndex - 1) % 26;
            $letters = chr(65 + $modulo) . $letters;
            $columnIndex = intdiv($columnIndex - 1, 26);
        }

        return $letters;
    }
}
