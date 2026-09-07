<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Security;

use EvilStudio\ComposerParser\Service\Security\VersionComparator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VersionComparatorTest extends TestCase
{
    #[DataProvider('versionProvider')]
    public function testIsOlderThan(string $installedVersion, string $fixedVersion, ?bool $expected): void
    {
        self::assertSame($expected, (new VersionComparator())->isOlderThan($installedVersion, $fixedVersion));
    }

    public static function versionProvider(): array
    {
        return [
            'older patch' => ['2.18.0', '2.19.0', true],
            'equal is not older' => ['1.7.4', '1.7.4', false],
            'newer minor beats lexical order' => ['8.14.5', '8.1.8', false],
            'newer minor with magento patch suffix' => ['6.11.2-p1', '6.2.25.2', false],
            'magento patch suffix ranks above plain release' => ['100.4.8', '100.4.8-p5', true],
            'magento patch suffix is not older than plain release' => ['100.4.8-p5', '100.4.8', false],
            'leading v is stripped before comparing' => ['v2.5.3', '2.5.4', true],
            'leading v on an equal version' => ['v1.7.4', '1.7.4', false],
            'unrelated major' => ['118.0.3', '8.1.8', false],
            'dev branch cannot be compared' => ['dev-master', '2.19.0', null],
            'dev suffix cannot be compared' => ['1.x-dev', '2.19.0', null],
            'empty fixed version cannot be compared' => ['2.19.0', '', null],
            'empty installed version cannot be compared' => ['', '2.19.0', null],
            'date is not a version' => ['2.19.0', '2024-01-01', null],
        ];
    }
}
