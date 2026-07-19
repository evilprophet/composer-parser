<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Model;

use EvilStudio\ComposerParser\Model\PackageConfig;
use PHPUnit\Framework\TestCase;

class PackageConfigTest extends TestCase
{
    public function testPackageGroupsPreserveConfigurationOrderWhenPrioritiesAreEqual(): void
    {
        $packageConfig = new PackageConfig([
            'packageGroups' => [
                [
                    'name' => 'First',
                    'parserPriority' => 10,
                    'writerOrder' => 10,
                    'groupType' => 'require',
                    'regex' => '/.*/',
                ],
                [
                    'name' => 'Second',
                    'parserPriority' => 10,
                    'writerOrder' => 10,
                    'groupType' => 'require',
                    'regex' => '/.*/',
                ],
                [
                    'name' => 'Higher Parser Priority',
                    'parserPriority' => 20,
                    'writerOrder' => 20,
                    'groupType' => 'require',
                    'regex' => '/.*/',
                ],
            ],
        ]);

        self::assertSame(
            ['Higher Parser Priority', 'First', 'Second'],
            array_column($packageConfig->getPackageGroupsForParser(null), 'name')
        );
        self::assertSame(
            ['First', 'Second', 'Higher Parser Priority'],
            array_column($packageConfig->getPackageGroupsForWriter(), 'name')
        );
    }
}
