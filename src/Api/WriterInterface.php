<?php

namespace EvilStudio\ComposerParser\Api;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;

interface WriterInterface
{

    /**
     * @param ParsedDataInterface $parsedData
     */
    public function execute(ParsedDataInterface $parsedData): void;
}