<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Support;

use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\ProviderInterface;

class InMemoryProvider implements ProviderInterface
{
    protected string $currentProjectName = '';

    /**
     * @param array<string, array{composerJson?: array, composerLock?: array}> $repositoryData
     */
    public function __construct(protected array $repositoryData)
    {
    }

    public function load(RepositoryInterface $repository): void
    {
        $this->currentProjectName = $repository->getProjectName();
    }

    public function getComposerJsonContent(): array
    {
        return $this->repositoryData[$this->currentProjectName]['composerJson'] ?? [];
    }

    public function getComposerLockContent(): array
    {
        return $this->repositoryData[$this->currentProjectName]['composerLock'] ?? [];
    }
}
