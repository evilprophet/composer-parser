<?php

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Api\ParserInterface;
use EvilStudio\ComposerParser\Exception\ParserTypeNotSupportedException;

class ParserManager
{
    protected string $parserType;

    protected array $parsers;

    public function __construct(string $parserType, array $parsers)
    {
        $this->parserType = $parserType;
        $this->parsers = $parsers;
    }

    public function getParser(): ParserInterface
    {
        if (!key_exists($this->parserType, $this->parsers)) {
            throw new ParserTypeNotSupportedException();
        }

        return $this->parsers[$this->parserType];
    }
}