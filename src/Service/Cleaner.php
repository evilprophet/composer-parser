<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service;

use EvilStudio\ComposerParser\Api\Data\RepositoryListInterface;
use EvilStudio\ComposerParser\Service\Repository\RepositoryDirectoryPath;
use Symfony\Component\Filesystem\Filesystem;

class Cleaner
{
    public function __construct(
        protected RepositoryListInterface $repositoryList,
        protected string $appDir
    ) {
    }

    public function execute(): void
    {
        $filesystem = new Filesystem();

        foreach ($this->repositoryList->getList() as $repository) {
            $filesystem->remove(RepositoryDirectoryPath::resolve($this->appDir, $repository->getDirectory()));
        }
    }
}
