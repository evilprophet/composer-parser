<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Provider;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository as Repository;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;

class GitRepository extends AbstractProvider
{
    protected const string GIT_METADATA_DIRECTORY = '.git';

    protected Repository $gitRepository;

    public function __construct(string $appDir, protected Git $git)
    {
        parent::__construct($appDir);
    }

    public function load(RepositoryInterface $repository): void
    {
        $this->localRepositoryDirectory = $this->resolveLocalRepositoryDirectory($repository);
        $gitMetadataDirectory = $this->localRepositoryDirectory . DIRECTORY_SEPARATOR . self::GIT_METADATA_DIRECTORY;

        if (is_dir($gitMetadataDirectory)) {
            $this->gitRepository = $this->git->open($this->localRepositoryDirectory);
        } else {
            $this->gitRepository = $this->git->cloneRepository(
                $repository->getRemote(),
                $this->localRepositoryDirectory
            );
        }

        $this->gitRepository->checkout($repository->getBranch());
    }
}
