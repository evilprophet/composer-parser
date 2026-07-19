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
}
