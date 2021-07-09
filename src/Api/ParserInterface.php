<?php

namespace EvilStudio\ComposerParser\Api;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;

interface ParserInterface
{
    /**
     * @return ParsedDataInterface
     */
    public function execute(): ParsedDataInterface;
}