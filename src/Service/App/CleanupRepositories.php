<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\App;

use EvilStudio\ComposerParser\Service\Cleaner;

class CleanupRepositories
{
    public function __construct(protected Cleaner $cleaner)
    {
    }

    public function execute(): void
    {
        $this->cleaner->execute();
    }
}
