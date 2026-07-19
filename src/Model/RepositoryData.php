<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Model;

class RepositoryData
{
    public function __construct(
        protected array $composerJson,
        protected array $composerLock = []
    ) {
    }

    public function getComposerJson(): array
    {
        return $this->composerJson;
    }

    public function getComposerLock(): array
    {
        return $this->composerLock;
    }
}
