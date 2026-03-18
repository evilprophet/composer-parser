<?php

declare(strict_types=1);

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
        $decoded = json_decode($composerJsonFileContent, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function getComposerLockContent(): array
    {
        $composerLockFilePath = sprintf(self::COMPOSER_LOCK_PATH, $this->localRepositoryDirectory);
        $composerLockFileContent = file_get_contents($composerLockFilePath);
        $decoded = $composerLockFileContent !== false ? json_decode($composerLockFileContent, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
