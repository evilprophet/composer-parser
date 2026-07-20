<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Model;

use EvilStudio\ComposerParser\Model\StylingConfig;
use PHPUnit\Framework\TestCase;

class StylingConfigTest extends TestCase
{
    public function testMissingStylingValuesUseEmptyDefaults(): void
    {
        $stylingConfig = new StylingConfig([]);

        self::assertSame('', $stylingConfig->getGroupHeaderBackgroundColor());
        self::assertSame([], $stylingConfig->getCellStyleMapping());
    }

    public function testNormalizesHashPrefixedColors(): void
    {
        $stylingConfig = new StylingConfig([
            'groupHeaderBackgroundColor' => '#999999',
            'cellStyleMapping' => [
                [
                    'color' => '#FF0000',
                    'backgroundColor' => '#FFFF00',
                ],
            ],
        ]);

        self::assertSame('999999', $stylingConfig->getGroupHeaderBackgroundColor());
        self::assertSame([
            [
                'color' => 'FF0000',
                'backgroundColor' => 'FFFF00',
            ],
        ], $stylingConfig->getCellStyleMapping());
    }
}
