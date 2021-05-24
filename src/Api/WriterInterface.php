<?php

namespace EvilStudio\ComposerParser\Api;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;

interface WriterInterface
{

    /**
     * @param ParsedDataInterface $parsedData
     */
    public function write(ParsedDataInterface $parsedData): void;
}