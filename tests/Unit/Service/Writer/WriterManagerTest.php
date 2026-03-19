<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Writer;

use EvilStudio\ComposerParser\Api\WriterInterface;
use EvilStudio\ComposerParser\Exception\WriterTypeNotSupportedException;
use EvilStudio\ComposerParser\Service\Writer\WriterManager;
use PHPUnit\Framework\TestCase;

class WriterManagerTest extends TestCase
{
    public function testGetWriterReturnsConfiguredWriter(): void
    {
        $writer = $this->createStub(WriterInterface::class);
        $manager = new WriterManager('xlsx', ['xlsx' => $writer]);

        self::assertSame($writer, $manager->getWriter());
    }

    public function testGetWriterThrowsWhenWriterTypeIsUnknown(): void
    {
        $manager = new WriterManager('unknown', []);

        $this->expectException(WriterTypeNotSupportedException::class);

        $manager->getWriter();
    }
}
