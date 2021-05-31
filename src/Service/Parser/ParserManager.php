<?php

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Api\ParserInterface;
use EvilStudio\ComposerParser\Exception\ParserTypeNotSupportedException;

class ParserManager
{
    /**
     * @var string
     */
    protected $parserType;

    /**
     * @var array
     */
    protected $parsers;

    /**
     * ParserManager constructor.
     * @param string $parserType
     * @param array $parsers
     */
    public function __construct(string $parserType, array $parsers)
    {
        $this->parserType = $parserType;
        $this->parsers = $parsers;
    }

    /**
     * @return ParserInterface
     * @throws ParserTypeNotSupportedException
     */
    public function getParser(): ParserInterface
    {
        if (!key_exists($this->parserType, $this->parsers)) {
            throw new ParserTypeNotSupportedException();
        }

        return $this->parsers[$this->parserType];
    }
}