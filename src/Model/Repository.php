<?php

namespace EvilStudio\ComposerParser\Model;

use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;

class Repository implements RepositoryInterface
{
    /**
     * @var string
     */
    protected $projectName;

    /**
     * @var string
     */
    protected $repositoryName;

    /**
     * @var string
     */
    protected $remote;

    /**
     * @var string
     */
    protected $branch;

    /**
     * @var string
     */
    protected $directory;

    /**
     * Repository constructor.
     * @param array $repositoryConfig
     */
    public function __construct(array $repositoryConfig)
    {
        $this->projectName = $repositoryConfig['name'];
        $this->remote = $repositoryConfig['remote'];
        $this->branch = $repositoryConfig['branch'];
        $this->directory = $repositoryConfig['directory'];

        preg_match('/:(.*\/.*)\.git/', $this->remote, $repositoryName);
        $this->repositoryName = $repositoryName[1];
    }

    /**
     * @return string
     */
    public function getProjectName(): string
    {
        return $this->projectName;
    }

    /**
     * @return string
     */
    public function getRepositoryName(): string
    {
        return $this->repositoryName;
    }

    /**
     * @return string
     */
    public function getRemote(): string
    {
        return $this->remote;
    }

    /**
     * @return string
     */
    public function getBranch(): string
    {
        return $this->branch;
    }

    /**
     * @return string
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }
}