<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Parser;

use EvilStudio\ComposerParser\Api\ParserInterface;
use EvilStudio\ComposerParser\Exception\ParserTypeNotSupportedException;
use EvilStudio\ComposerParser\Service\Parser\ParserManager;
use PHPUnit\Framework\TestCase;

class ParserManagerTest extends TestCase
{
    public function testGetParserReturnsConfiguredParser(): void
    {
        $parser = $this->createStub(ParserInterface::class);
        $manager = new ParserManager('composerJson', ['composerJson' => $parser]);

        self::assertSame($parser, $manager->getParser());
    }

    public function testGetParserThrowsWhenParserTypeIsUnknown(): void
    {
        $manager = new ParserManager('unknown', []);

        $this->expectException(ParserTypeNotSupportedException::class);

        $manager->getParser();
    }
}
