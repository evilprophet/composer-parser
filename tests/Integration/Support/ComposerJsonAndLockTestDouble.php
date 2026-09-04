<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Support;

use EvilStudio\ComposerParser\Service\Parser\ComposerJsonAndLock;

class ComposerJsonAndLockTestDouble extends ComposerJsonAndLock
{
    public function getCollectedInstalledPackageVersionsByProject(): array
    {
        return $this->installedPackageVersionsByProject;
    }
}
