<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Writer;

use EvilStudio\ComposerParser\Api\WriterInterface;
use EvilStudio\ComposerParser\Exception\WriterTypeNotSupportedException;
use EvilStudio\ComposerParser\Service\Writer\WriterManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class WriterManagerTest extends TestCase
{
    public function testGetWriterReturnsConfiguredWriter(): void
    {
        $writer = $this->createStub(WriterInterface::class);
        $unusedFactoryCalled = false;
        $manager = new WriterManager('xlsx', new ServiceLocator([
            'xlsx' => static fn () => $writer,
            'googleSheets' => static function () use (&$unusedFactoryCalled, $writer): WriterInterface {
                $unusedFactoryCalled = true;

                return $writer;
            },
        ]));

        self::assertSame($writer, $manager->getWriter());
        self::assertFalse($unusedFactoryCalled);
    }

    public function testGetWriterThrowsWhenWriterTypeIsUnknown(): void
    {
        $manager = new WriterManager('unknown', new ServiceLocator([]));

        $this->expectException(WriterTypeNotSupportedException::class);

        $manager->getWriter();
    }
}
