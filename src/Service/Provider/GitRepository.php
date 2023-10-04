<?php

namespace EvilStudio\ComposerParser\Service\Provider;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository as Repository;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;

class GitRepository extends AbstractProvider
{
    protected Git $git;

    protected Repository $gitRepository;

    public function __construct(string $appDir)
    {
        parent::__construct($appDir);

        $this->git = new Git();
    }

    public function load(RepositoryInterface $repository): void
    {
        $this->localRepositoryDirectory = sprintf(self::LOCAL_REPOSITORY_DIRECTORY_PATH, $this->appDir, $repository->getDirectory());

        try {
            $this->gitRepository = $this->git->cloneRepository($repository->getRemote(), $this->localRepositoryDirectory);
        } catch (GitException $exception) {
            $this->gitRepository = $this->git->open($this->localRepositoryDirectory);
        }

        $this->gitRepository->checkout($repository->getBranch());
    }
}