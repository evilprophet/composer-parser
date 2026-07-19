<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Model\RepositoryData;

class RepositoryDataFactory
{
    public function create(array $composerJson, array $composerLock = []): RepositoryData
    {
        return new RepositoryData($composerJson, $composerLock);
    }
}
