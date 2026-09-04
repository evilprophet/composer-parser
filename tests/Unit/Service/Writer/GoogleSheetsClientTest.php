<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Writer;

use EvilStudio\ComposerParser\Service\Writer\GoogleSheetsClient;
use PHPUnit\Framework\TestCase;

class GoogleSheetsClientTest extends TestCase
{
    public function testFormattingRequestBatchesAreBoundedAndPreserveAllRequests(): void
    {
        $cellStyles = [];
        $notes = [];

        for ($row = 2; $row <= 202; $row++) {
            $cellStyles[] = [
                'row' => $row,
                'stylesByColumn' => [
                    2 => ['fontColor' => 'FF0000'],
                ],
            ];
            $notes[] = [
                'row' => $row,
                'notesByColumn' => [
                    2 => sprintf('Note for row %d', $row),
                ],
            ];
        }

        $batches = $this->client()->getFormattingRequestBatchesForTest(1, [['Last update', 'project-a', 'project-b']], [], '999999', $cellStyles, $notes);

        self::assertSame([100, 100, 100, 100, 7], array_map(static fn (array $batch): int => count($batch), $batches));

        $requests = $this->flattenBatches($batches);
        self::assertCount(407, $requests);
        self::assertCount(201, array_filter($requests, static fn (array $request): bool => isset($request['updateCells'])));
    }

    public function testFormattingRequestsGroupContiguousStylesAndNotesInOneRow(): void
    {
        $batches = $this->client()->getFormattingRequestBatchesForTest(
            1,
            [['Last update', 'project-a', 'project-b', 'project-c', 'project-d']],
            [],
            '999999',
            [
                [
                    'row' => 2,
                    'stylesByColumn' => [
                        2 => ['fontColor' => 'FF0000'],
                        3 => ['fontColor' => 'FF0000'],
                        5 => ['backgroundColor' => '00FF00'],
                    ],
                ],
            ],
            [
                [
                    'row' => 2,
                    'notesByColumn' => [
                        2 => 'First note',
                        3 => 'Second note',
                        5 => 'Third note',
                    ],
                ],
            ]
        );

        $requests = $this->flattenBatches($batches);
        $styleRequests = array_values(array_filter($requests, static function (array $request): bool {
            return isset($request['repeatCell']) && $request['repeatCell']['range']['startRowIndex'] === 1;
        }));
        $noteRequests = array_values(array_filter($requests, static fn (array $request): bool => isset($request['updateCells'])));

        self::assertCount(2, $styleRequests);
        self::assertSame(1, $styleRequests[0]['repeatCell']['range']['startColumnIndex']);
        self::assertSame(3, $styleRequests[0]['repeatCell']['range']['endColumnIndex']);
        self::assertSame(4, $styleRequests[1]['repeatCell']['range']['startColumnIndex']);
        self::assertSame(5, $styleRequests[1]['repeatCell']['range']['endColumnIndex']);

        self::assertCount(2, $noteRequests);
        self::assertSame(1, $noteRequests[0]['updateCells']['range']['startColumnIndex']);
        self::assertSame(3, $noteRequests[0]['updateCells']['range']['endColumnIndex']);
        self::assertSame('First note', $noteRequests[0]['updateCells']['rows'][0]['values'][0]['note']);
        self::assertSame('Second note', $noteRequests[0]['updateCells']['rows'][0]['values'][1]['note']);
        self::assertSame(4, $noteRequests[1]['updateCells']['range']['startColumnIndex']);
        self::assertSame(5, $noteRequests[1]['updateCells']['range']['endColumnIndex']);
        self::assertSame('Third note', $noteRequests[1]['updateCells']['rows'][0]['values'][0]['note']);
    }

    protected function client(): GoogleSheetsClient
    {
        return new class extends GoogleSheetsClient {
            public function getFormattingRequestBatchesForTest(int $sheetId, array $values, array $groupRows, string $groupHeaderBackgroundColor, array $cellStyles, array $notes): array
            {
                return iterator_to_array($this->getFormattingRequestBatches($sheetId, $values, $groupRows, $groupHeaderBackgroundColor, $cellStyles, $notes), false);
            }
        };
    }

    protected function flattenBatches(array $batches): array
    {
        $requests = [];

        foreach ($batches as $batch) {
            foreach ($batch as $request) {
                $requests[] = $request;
            }
        }

        return $requests;
    }
}
