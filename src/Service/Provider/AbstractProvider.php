<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Provider;

use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\ProviderInterface;
use EvilStudio\ComposerParser\Service\Repository\RepositoryDirectoryPath;
use JsonException;
use RuntimeException;

abstract class AbstractProvider implements ProviderInterface
{
    protected const string REQUIRED_FILE_READ_ERROR = 'Required Composer file "%s" could not be read.';
    protected const string INVALID_JSON_ERROR = 'Composer file "%s" contains invalid JSON: %s';
    protected const string INVALID_JSON_STRUCTURE_ERROR = 'Composer file "%s" must contain a JSON object.';

    protected string $appDir;
    protected string $localRepositoryDirectory;

    public function __construct(string $appDir)
    {
        $this->appDir = $appDir;
    }

    protected function resolveLocalRepositoryDirectory(RepositoryInterface $repository): string
    {
        return RepositoryDirectoryPath::resolve($this->appDir, $repository->getDirectory());
    }

    public function getComposerJsonContent(): array
    {
        $composerJsonFilePath = sprintf(self::COMPOSER_JSON_PATH, $this->localRepositoryDirectory);

        return $this->readJsonFile($composerJsonFilePath, true);
    }

    public function getComposerLockContent(): array
    {
        $composerLockFilePath = sprintf(self::COMPOSER_LOCK_PATH, $this->localRepositoryDirectory);

        return $this->readJsonFile($composerLockFilePath, false);
    }

    public function getLocalRepositoryDirectory(): string
    {
        return $this->localRepositoryDirectory;
    }

    protected function readJsonFile(string $filePath, bool $required): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            if ($required) {
                throw new RuntimeException(sprintf(self::REQUIRED_FILE_READ_ERROR, basename($filePath)));
            }

            return [];
        }

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            if ($required) {
                throw new RuntimeException(sprintf(self::REQUIRED_FILE_READ_ERROR, basename($filePath)));
            }

            return [];
        }

        try {
            $decoded = json_decode($fileContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf(
                self::INVALID_JSON_ERROR,
                basename($filePath),
                $exception->getMessage()
            ), 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf(self::INVALID_JSON_STRUCTURE_ERROR, basename($filePath)));
        }

        return $decoded;
    }
}
