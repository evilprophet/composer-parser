<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Model;

use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;

class Repository implements RepositoryInterface
{
    protected string $projectName;
    protected string $repositoryName;
    protected string $remoteProjectName;
    protected string $remote;
    protected string $branch;
    protected string $directory;

    public function __construct(array $repositoryConfig)
    {
        $this->projectName = $repositoryConfig['name'];
        $this->remote = $repositoryConfig['remote'];
        $this->branch = $repositoryConfig['branch'];
        $this->directory = $repositoryConfig['directory'];

        $repositoryName = $this->extractRepositoryName($this->remote);
        if ($repositoryName === '') {
            throw new \InvalidArgumentException('Unsupported git remote format: ' . $this->remote);
        }

        $this->repositoryName = $repositoryName;
        $this->remoteProjectName = basename($this->repositoryName);
    }

    public function getProjectName(): string
    {
        return $this->projectName;
    }

    public function getRepositoryName(): string
    {
        return $this->repositoryName;
    }

    public function getRemoteProjectName(): string
    {
        return $this->remoteProjectName;
    }

    public function getRemote(): string
    {
        return $this->remote;
    }

    public function getBranch(): string
    {
        return $this->branch;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    protected function extractRepositoryName(string $remote): string
    {
        $matches = [];

        if (preg_match('/^[^@]+@[^:]+:(?<path>.+?)(?:\.git)?$/', $remote, $matches) === 1) {
            return trim((string) ($matches['path'] ?? ''), '/');
        }

        if (preg_match('#^(?:https?|ssh)://[^/]+/(?<path>.+?)(?:\.git)?/?$#', $remote, $matches) === 1) {
            return trim((string) ($matches['path'] ?? ''), '/');
        }

        return '';
    }
}
