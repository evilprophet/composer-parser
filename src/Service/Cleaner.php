<?php

namespace EvilStudio\ComposerParser\Service;

use Symfony\Component\Filesystem\Filesystem;

class Cleaner
{
    /**
     * @var array
     */
    protected $repositoriesConfig;

    /**
     * Cleaner constructor.
     * @param array $repositoriesConfig
     */
    public function __construct(array $repositoriesConfig)
    {
        $this->repositoriesConfig = $repositoriesConfig;
    }

    public function execute(): void
    {
        $filesystem = new Filesystem();
        foreach ($this->repositoriesConfig as $repositoryName => $repositoryConfig) {
            $filesystem->remove($repositoryConfig['directory']);

        }
    }
}