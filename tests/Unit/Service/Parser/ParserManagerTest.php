<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Parser;

use EvilStudio\ComposerParser\Api\ParserInterface;
use EvilStudio\ComposerParser\Exception\ParserTypeNotSupportedException;
use EvilStudio\ComposerParser\Service\Parser\ParserManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ParserManagerTest extends TestCase
{
    public function testGetParserReturnsConfiguredParser(): void
    {
        $parser = $this->createStub(ParserInterface::class);
        $unusedFactoryCalled = false;
        $manager = new ParserManager('composerJson', new ServiceLocator([
            'composerJson' => static fn () => $parser,
            'composerFull' => static function () use (&$unusedFactoryCalled, $parser): ParserInterface {
                $unusedFactoryCalled = true;

                return $parser;
            },
        ]));

        self::assertSame($parser, $manager->getParser());
        self::assertFalse($unusedFactoryCalled);
    }

    public function testGetParserThrowsWhenParserTypeIsUnknown(): void
    {
        $manager = new ParserManager('unknown', new ServiceLocator([]));

        $this->expectException(ParserTypeNotSupportedException::class);

        $manager->getParser();
    }
}
