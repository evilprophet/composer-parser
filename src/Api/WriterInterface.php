<?php

namespace EvilStudio\ComposerParser\Api;

interface WriterInterface
{

    /**
     * @param array $parsedData
     */
    public function write(array $parsedData): void;
}