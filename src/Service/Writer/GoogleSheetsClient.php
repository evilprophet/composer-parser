<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Writer;

use EvilStudio\ComposerParser\Api\GoogleSheetsClientInterface;
use Google\Service\Sheets;

class GoogleSheetsClient implements GoogleSheetsClientInterface
{
    protected const int PROJECT_COLUMN_PIXEL_SIZE = 95;
    protected const int FORMATTING_REQUEST_BATCH_SIZE = 100;

    public function write(string $serviceAccountJsonPath, string $spreadsheetId, string $sheetName, array $values, array $groupRows, string $groupHeaderBackgroundColor, array $cellStyles, array $notes): void
    {
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
                'sheetId' => (int)$properties->getSheetId(),
                'rowCount' => max(1, (int)($gridProperties->getRowCount() ?? 1)),
                'columnCount' => max(1, (int)($gridProperties->getColumnCount() ?? 1)),
            ];
        }

        return null;
    }

    protected function applyFormatting(Sheets $service, string $spreadsheetId, int $sheetId, array $values, array $groupRows, string $groupHeaderBackgroundColor, array $cellStyles, array $notes): void
    {
        foreach ($this->getFormattingRequestBatches($sheetId, $values, $groupRows, $groupHeaderBackgroundColor, $cellStyles, $notes) as $requests) {
            $service->spreadsheets->batchUpdate(
                $spreadsheetId,
                new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest(['requests' => $requests])
            );
        }
    }

    protected function getFormattingRequestBatches(int $sheetId, array $values, array $groupRows, string $groupHeaderBackgroundColor, array $cellStyles, array $notes): \Generator
    {
        $requests = [];

        foreach ($this->getFormattingRequests($sheetId, $values, $groupRows, $groupHeaderBackgroundColor, $cellStyles, $notes) as $request) {
            $requests[] = $request;

            if (count($requests) < self::FORMATTING_REQUEST_BATCH_SIZE) {
                continue;
            }

            yield $requests;
            $requests = [];
        }

        if ($requests !== []) {
            yield $requests;
        }
    }

    protected function getFormattingRequests(int $sheetId, array $values, array $groupRows, string $groupHeaderBackgroundColor, array $cellStyles, array $notes): \Generator
    {
        $columnCount = count($values[0] ?? []);
        if ($columnCount === 0) {
            return;
        }

        yield [
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

        yield [
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
            yield [
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

        yield [
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

        yield [
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
            yield [
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

        foreach ($this->getCellStyleRequests($sheetId, $cellStyles) as $request) {
            yield $request;
        }

        foreach ($this->getNoteUpdateRequests($sheetId, $notes) as $request) {
            yield $request;
        }
    }

    protected function getCellStyleRequests(int $sheetId, array $cellStyles): \Generator
    {
        foreach ($cellStyles as $cellStyleRow) {
            $row = (int)$cellStyleRow['row'];
            $stylesByColumn = $cellStyleRow['stylesByColumn'];
            ksort($stylesByColumn);

            $rangeStartColumn = null;
            $rangeEndColumn = null;
            $styleForRange = null;

            foreach ($stylesByColumn as $column => $cellStyle) {
                $column = (int)$column;

                if ($rangeStartColumn !== null && $column === $rangeEndColumn + 1 && $cellStyle === $styleForRange) {
                    $rangeEndColumn = $column;
                    continue;
                }

                if ($rangeStartColumn !== null) {
                    $request = $this->createCellStyleRequest($sheetId, $row, $rangeStartColumn, $rangeEndColumn, $styleForRange);
                    if ($request !== null) {
                        yield $request;
                    }
                }

                $rangeStartColumn = $column;
                $rangeEndColumn = $column;
                $styleForRange = $cellStyle;
            }

            if ($rangeStartColumn === null) {
                continue;
            }

            $request = $this->createCellStyleRequest($sheetId, $row, $rangeStartColumn, $rangeEndColumn, $styleForRange);
            if ($request !== null) {
                yield $request;
            }
        }
    }

    protected function createCellStyleRequest(int $sheetId, int $row, int $startColumn, int $endColumn, array $cellStyle): ?array
    {
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
            return null;
        }

        return [
            'repeatCell' => [
                'range' => [
                    'sheetId' => $sheetId,
                    'startRowIndex' => $row - 1,
                    'endRowIndex' => $row,
                    'startColumnIndex' => $startColumn - 1,
                    'endColumnIndex' => $endColumn,
                ],
                'cell' => [
                    'userEnteredFormat' => $cellFormat,
                ],
                'fields' => implode(',', $fields),
            ],
        ];
    }

    protected function getNoteUpdateRequests(int $sheetId, array $notes): \Generator
    {
        foreach ($notes as $noteRow) {
            $row = (int)$noteRow['row'];
            $notesByColumn = $noteRow['notesByColumn'];
            ksort($notesByColumn);

            $rangeStartColumn = null;
            $previousColumn = null;
            $notesForRange = [];

            foreach ($notesByColumn as $column => $note) {
                $column = (int)$column;

                if ($rangeStartColumn !== null && $column !== $previousColumn + 1) {
                    yield $this->createNoteUpdateRequest($sheetId, $row, $rangeStartColumn, $notesForRange);
                    $rangeStartColumn = null;
                    $notesForRange = [];
                }

                if ($rangeStartColumn === null) {
                    $rangeStartColumn = $column;
                }

                $notesForRange[] = $note;
                $previousColumn = $column;
            }

            if ($rangeStartColumn !== null) {
                yield $this->createNoteUpdateRequest($sheetId, $row, $rangeStartColumn, $notesForRange);
            }
        }
    }

    protected function createNoteUpdateRequest(int $sheetId, int $row, int $startColumn, array $notes): array
    {
        $values = [];
        foreach ($notes as $note) {
            $values[] = ['note' => $note];
        }

        return [
            'updateCells' => [
                'range' => [
                    'sheetId' => $sheetId,
                    'startRowIndex' => $row - 1,
                    'endRowIndex' => $row,
                    'startColumnIndex' => $startColumn - 1,
                    'endColumnIndex' => $startColumn - 1 + count($values),
                ],
                'rows' => [
                    [
                        'values' => $values,
                    ],
                ],
                'fields' => 'note',
            ],
        ];
    }

    protected function clearSheetFormattingAndNotes(\Google\Service\Sheets $service, string $spreadsheetId, int $sheetId, int $rowCount, int $columnCount): void
    {
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
