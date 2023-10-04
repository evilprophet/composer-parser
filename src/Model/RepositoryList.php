<?php

namespace EvilStudio\ComposerParser\Model;

use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryListInterface;

class RepositoryList implements RepositoryListInterface
{
    protected array $repositoryList = [];

    protected array $projectNamesList = [];

    public function __construct(array $repositoryList)
    {
        foreach ($repositoryList as $repository) {
            $this->repositoryList[] = new Repository($repository);
        }
    }

    public function getList(): array
    {
        return $this->repositoryList;
    }

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