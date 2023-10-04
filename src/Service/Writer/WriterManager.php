<?php

namespace EvilStudio\ComposerParser\Service\Writer;

use EvilStudio\ComposerParser\Api\WriterInterface;
use EvilStudio\ComposerParser\Exception\WriterTypeNotSupportedException;

class WriterManager
{
    protected string $writerType;

    protected array $writers;

    public function __construct(string $writerType, array $writers)
    {
        $this->writerType = $writerType;
        $this->writers = $writers;
    }

    public function getWriter(): WriterInterface
    {
        if (!key_exists($this->writerType, $this->writers)) {
            throw new WriterTypeNotSupportedException();
        }

        return $this->writers[$this->writerType];
    }
}