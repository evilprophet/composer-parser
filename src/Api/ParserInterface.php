<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Api;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;

interface ParserInterface
{
    public function execute(): ParsedDataInterface;
}
