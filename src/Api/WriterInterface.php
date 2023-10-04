<?php

namespace EvilStudio\ComposerParser\Api;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;

interface WriterInterface
{
    public function execute(ParsedDataInterface $parsedData): void;
}