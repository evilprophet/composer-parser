<?php

namespace EvilStudio\ComposerParser\Service\Provider;

use Cz\Git\GitException;
use Cz\Git\GitRepository as Git;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\ProviderInterface;

class GitRepository implements ProviderInterface
{
    /**
     * @var string
     */
    protected $appDir;

    /**
     * @var string
     */
    protected $repository;

    /**
     * @var string
     */
    protected $localRepositoryDirectory;

    /**
     * GitRepository constructor.
     * @param string $appDir
     */
    public function __construct(string $appDir)
    {
        $this->appDir = $appDir;
    }

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

    /**
     * @return array
     */
    public function getComposerJsonContent(): array
    {
        $composerJsonFilePath = sprintf(self::COMPOSER_JSON_PATH, $this->localRepositoryDirectory);
        $composerJsonFileContent = file_get_contents($composerJsonFilePath);

        return json_decode($composerJsonFileContent, true);
    }

    /**
     * @return array
     */
    public function getComposerLockContent(): array
    {
        $composerLockFilePath = sprintf(self::COMPOSER_LOCK_PATH, $this->localRepositoryDirectory);
        $composerLockFileContent = file_get_contents($composerLockFilePath);

        return json_decode($composerLockFileContent, true);
    }
}