<?php

namespace EvilStudio\ComposerParser\Api;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;

interface ParserInterface
{
    public function execute(): ParsedDataInterface;
}