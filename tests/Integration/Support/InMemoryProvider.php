<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Support;

use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\ProviderInterface;

class InMemoryProvider implements ProviderInterface
{
    protected string $currentProjectName = '';
    protected array $loadedProjectNames = [];
    protected array $composerLockReadProjectNames = [];

    /**
     * @param array<string, array{composerJson?: array, composerLock?: array}> $repositoryData
     */
    public function __construct(protected array $repositoryData, protected string $localRepositoryDirectory = '')
    {
    }

    public function load(RepositoryInterface $repository): void
    {
        $this->currentProjectName = $repository->getProjectName();
        $this->loadedProjectNames[] = $this->currentProjectName;
    }

    public function getComposerJsonContent(): array
    {
        return $this->repositoryData[$this->currentProjectName]['composerJson'] ?? [];
    }

    public function getComposerLockContent(): array
    {
        return $this->repositoryData[$this->currentProjectName]['composerLock'] ?? [];
    }

    public function getComposerLockContentForRepository(RepositoryInterface $repository): array
    {
        $projectName = $repository->getProjectName();
        $this->composerLockReadProjectNames[] = $projectName;

        return $this->repositoryData[$projectName]['composerLock'] ?? [];
    }

    public function getLocalRepositoryDirectory(): string
    {
        return $this->localRepositoryDirectory;
    }

    public function getLocalRepositoryDirectoryForRepository(RepositoryInterface $repository): string
    {
        return $this->localRepositoryDirectory;
    }

    public function getLoadedProjectNames(): array
    {
        return $this->loadedProjectNames;
    }

    public function getComposerLockReadProjectNames(): array
    {
        return $this->composerLockReadProjectNames;
    }
}
