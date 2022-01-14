<?php

namespace EvilStudio\ComposerParser\Service;

use EvilStudio\ComposerParser\Api\Data\RepositoryListInterface;
use Symfony\Component\Filesystem\Filesystem;

class Cleaner
{
    /**
     * @var RepositoryListInterface
     */
    protected $repositoryList;

    /**
     * Cleaner constructor.
     * @param RepositoryListInterface $repositoryList
     */
    public function __construct(RepositoryListInterface $repositoryList)
    {
        $this->repositoryList = $repositoryList;
    }

    public function execute(): void
    {
        $filesystem = new Filesystem();

        foreach ($this->repositoryList->getList() as $repository) {
            $filesystem->remove($repository->getDirectory());
        }
    }
}