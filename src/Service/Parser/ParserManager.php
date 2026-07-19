<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Api\ParserInterface;
use EvilStudio\ComposerParser\Exception\ParserTypeNotSupportedException;
use Psr\Container\ContainerInterface;

class ParserManager
{
    protected string $parserType;
    protected ContainerInterface $parsers;

    public function __construct(string $parserType, ContainerInterface $parsers)
    {
        $this->parserType = $parserType;
        $this->parsers = $parsers;
    }

    public function getParser(): ParserInterface
    {
        if (!$this->parsers->has($this->parserType)) {
            throw new ParserTypeNotSupportedException();
        }

        $parser = $this->parsers->get($this->parserType);
        if (!$parser instanceof ParserInterface) {
            throw new ParserTypeNotSupportedException();
        }

        return $parser;
    }
}
