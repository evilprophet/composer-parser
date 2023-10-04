<?php

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

        preg_match('/:(.*\/.*)\.git/', $this->remote, $repositoryName);
        $this->repositoryName = $repositoryName[1];
        $this->remoteProjectName = explode('/', $this->repositoryName)[1];
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
}