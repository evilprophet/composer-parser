<?php

namespace EvilStudio\ComposerParser\Model;

use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryListInterface;

class RepositoryList implements RepositoryListInterface
{
    /**
     * @var RepositoryListInterface[]
     */
    protected $repositoryList = [];

    /**
     * @var array
     */
    protected $projectNamesList = [];

    /**
     * RepositoryList constructor.
     * @param array $repositoryList
     */
    public function __construct(array $repositoryList)
    {
        foreach ($repositoryList as $repository) {
            $this->repositoryList[] = new Repository($repository);
        }
    }

    /**
     * @return RepositoryInterface[]
     */
    public function getList(): array
    {
        return $this->repositoryList;
    }

    /**
     * @return array
     */
    public function getProjectNames(): array
    {
        if (!empty($this->projectNamesList)) {
            return $this->projectNamesList;
        }

        /** @var RepositoryInterface $repository */
        foreach ($this->getList() as $repository) {
            $this->projectNamesList[] = $repository->getProjectName();
        }
        sort($this->projectNamesList);

        return $this->projectNamesList;
    }
}