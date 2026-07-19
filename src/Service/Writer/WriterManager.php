<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Writer;

use EvilStudio\ComposerParser\Api\WriterInterface;
use EvilStudio\ComposerParser\Exception\WriterTypeNotSupportedException;
use Psr\Container\ContainerInterface;

class WriterManager
{
    protected string $writerType;
    protected ContainerInterface $writers;

    public function __construct(string $writerType, ContainerInterface $writers)
    {
        $this->writerType = $writerType;
        $this->writers = $writers;
    }

    public function getWriter(): WriterInterface
    {
        if (!$this->writers->has($this->writerType)) {
            throw new WriterTypeNotSupportedException();
        }

        $writer = $this->writers->get($this->writerType);
        if (!$writer instanceof WriterInterface) {
            throw new WriterTypeNotSupportedException();
        }

        return $writer;
    }
}
