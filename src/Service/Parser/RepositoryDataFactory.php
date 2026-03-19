<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Model\RepositoryData;
use InvalidArgumentException;

class RepositoryDataFactory
{
    public function create(array $composerJson, array $composerLock = []): RepositoryData
    {
        if (!is_array($composerJson)) {
            throw new InvalidArgumentException('composer.json data must be an array.');
        }

        if (!is_array($composerLock)) {
            throw new InvalidArgumentException('composer.lock data must be an array.');
        }

        return new RepositoryData($composerJson, $composerLock);
    }
}
