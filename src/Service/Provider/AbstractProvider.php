<?php

namespace EvilStudio\ComposerParser\Service\Provider;

use EvilStudio\ComposerParser\Api\ProviderInterface;

abstract class AbstractProvider implements ProviderInterface
{
    /**
     * @var string
     */
    protected $appDir;

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
        $composerLockFileContent = @file_get_contents($composerLockFilePath);

        return $composerLockFileContent ? json_decode($composerLockFileContent, true) : [];
    }
}