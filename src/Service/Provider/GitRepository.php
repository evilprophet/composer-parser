<?php

namespace EvilStudio\ComposerParser\Service\Provider;

use Cz\Git\GitException;
use Cz\Git\GitRepository as Git;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;

class GitRepository extends AbstractProvider
{
    /**
     * @var Git
     */
    protected $repository;

    /**
     * @param RepositoryInterface $repository
     * @throws GitException
     */
    public function load(RepositoryInterface $repository): void
    {
        $this->localRepositoryDirectory = sprintf('%s/%s', $this->appDir, $repository->getDirectory());

        try {
            $this->repository = Git::cloneRepository($repository->getRemote(), $this->localRepositoryDirectory);
        } catch (GitException $exception) {
            $this->repository = new Git($this->localRepositoryDirectory);
        }

        $this->repository->checkout($repository->getBranch());
    }
}