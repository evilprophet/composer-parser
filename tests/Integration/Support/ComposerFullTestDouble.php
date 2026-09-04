<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Support;

use EvilStudio\ComposerParser\Service\Parser\ComposerFull;
use mikehaertl\shellcommand\Command;

class ComposerFullTestDouble extends ComposerFull
{
    protected Command $composerOutdatedCommand;
    protected array $composerOutdatedRepositoryDirectories = [];

    public function getComposerOutdatedRepositoryDirectories(): array
    {
        return $this->composerOutdatedRepositoryDirectories;
    }

    public function setComposerOutdatedCommand(Command $composerOutdatedCommand): void
    {
        $this->composerOutdatedCommand = $composerOutdatedCommand;
    }

    public function createDefaultComposerOutdatedCommand(string $repositoryDirectoryPath): Command
    {
        return parent::createComposerOutdatedCommand($repositoryDirectoryPath);
    }

    protected function createComposerOutdatedCommand(string $repositoryDirectoryPath): Command
    {
        $this->composerOutdatedRepositoryDirectories[] = $repositoryDirectoryPath;

        return $this->composerOutdatedCommand;
    }
}
