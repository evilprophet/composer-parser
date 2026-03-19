<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Support;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\WriterInterface;

class CapturingWriter implements WriterInterface
{
    protected ?ParsedDataInterface $capturedParsedData = null;

    public function execute(ParsedDataInterface $parsedData): void
    {
        $this->capturedParsedData = $parsedData;
    }

    public function getCapturedParsedData(): ?ParsedDataInterface
    {
        return $this->capturedParsedData;
    }
}
