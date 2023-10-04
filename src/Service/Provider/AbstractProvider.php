<?php

namespace EvilStudio\ComposerParser\Service\Provider;

use EvilStudio\ComposerParser\Api\ProviderInterface;

abstract class AbstractProvider implements ProviderInterface
{
    protected string $appDir;

    protected string $localRepositoryDirectory;

    public function __construct(string $appDir)
    {
        $this->appDir = $appDir;
    }

    public function getComposerJsonContent(): array
    {
        $composerJsonFilePath = sprintf(self::COMPOSER_JSON_PATH, $this->localRepositoryDirectory);
        $composerJsonFileContent = file_get_contents($composerJsonFilePath);

        return json_decode($composerJsonFileContent, true);
    }

    public function getComposerLockContent(): array
    {
        $composerLockFilePath = sprintf(self::COMPOSER_LOCK_PATH, $this->localRepositoryDirectory);
        $composerLockFileContent = @file_get_contents($composerLockFilePath);

        return $composerLockFileContent ? json_decode($composerLockFileContent, true) : [];
    }
}