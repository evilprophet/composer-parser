<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Config;

use InvalidArgumentException;

class ConfigValidator
{
    protected const array ALLOWED_PROVIDER_TYPES = ['gitRepository', 'gitlabApiFiles', 'gitlabApiArchive'];
    protected const array ALLOWED_PARSER_TYPES = ['composerJson', 'composerJsonAndLock', 'composerFull'];
    protected const array ALLOWED_WRITER_TYPES = ['xlsx', 'json', 'html', 'googleSheets'];
    protected const array ALLOWED_INSTALLED_VERSION_DISPLAY = ['value', 'comment'];

    public function validate(array $appConfig, array $packageConfig, array $writerConfig, array $repositoryConfig): void
    {
        $this->validateAppConfig($appConfig);
        $this->validatePackageConfig($packageConfig);
        $this->validateWriterConfig($writerConfig);
        $this->validateRepositoryConfig($repositoryConfig);
    }

    protected function validateAppConfig(array $appConfig): void
    {
        $this->assertStringInSet($appConfig, 'providerType', self::ALLOWED_PROVIDER_TYPES);
        $this->assertStringInSet($appConfig, 'parserType', self::ALLOWED_PARSER_TYPES);
        $this->assertStringInSet($appConfig, 'writerType', self::ALLOWED_WRITER_TYPES);

        if (!isset($appConfig['timezone']) || !is_string($appConfig['timezone']) || trim($appConfig['timezone']) === '') {
            throw new InvalidArgumentException('Invalid config: app.config.timezone is required and must be a non-empty string.');
        }

        if (!in_array($appConfig['timezone'], timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException(sprintf('Invalid config: app.config.timezone "%s" is not a valid timezone identifier.', $appConfig['timezone']));
        }
    }

    protected function validatePackageConfig(array $packageConfig): void
    {
        if (!array_key_exists('includeInstalledVersion', $packageConfig) || !is_bool($packageConfig['includeInstalledVersion'])) {
            throw new InvalidArgumentException('Invalid config: package.config.includeInstalledVersion must be a boolean.');
        }

        $this->assertStringInSet($packageConfig, 'installedVersionDisplayedIn', self::ALLOWED_INSTALLED_VERSION_DISPLAY, 'package.config');

        if (!isset($packageConfig['packageGroups']) || !is_array($packageConfig['packageGroups'])) {
            throw new InvalidArgumentException('Invalid config: package.config.packageGroups must be an array.');
        }

        foreach ($packageConfig['packageGroups'] as $index => $group) {
            if (!is_array($group)) {
                throw new InvalidArgumentException(sprintf('Invalid config: package.config.packageGroups[%d] must be an array.', $index));
            }

            foreach (['name', 'groupType', 'regex'] as $requiredField) {
                if (!isset($group[$requiredField]) || !is_string($group[$requiredField]) || $group[$requiredField] === '') {
                    throw new InvalidArgumentException(sprintf('Invalid config: package.config.packageGroups[%d].%s is required and must be a non-empty string.', $index, $requiredField));
                }
            }

            if (!$this->isValidRegex($group['regex'])) {
                throw new InvalidArgumentException(sprintf('Invalid config: package.config.packageGroups[%d].regex is not a valid regex.', $index));
            }
        }
    }

    protected function validateWriterConfig(array $writerConfig): void
    {
        if (!isset($writerConfig['local']) || !is_array($writerConfig['local'])) {
            throw new InvalidArgumentException('Invalid config: writer.config.local must be an array.');
        }

        foreach (['fileName', 'fileDirectory', 'sheetName'] as $requiredField) {
            if (!isset($writerConfig['local'][$requiredField]) || !is_string($writerConfig['local'][$requiredField]) || $writerConfig['local'][$requiredField] === '') {
                throw new InvalidArgumentException(sprintf('Invalid config: writer.config.local.%s is required and must be a non-empty string.', $requiredField));
            }
        }

        if (isset($writerConfig['googleSheets']) && !is_array($writerConfig['googleSheets'])) {
            throw new InvalidArgumentException('Invalid config: writer.config.googleSheets must be an array.');
        }
    }

    public function validateGoogleSheetsWriterConfig(array $writerConfig): void
    {
        $googleSheetsConfig = $writerConfig['googleSheets'] ?? [];
        if (!is_array($googleSheetsConfig)) {
            throw new InvalidArgumentException('Invalid config: writer.config.googleSheets must be an array.');
        }

        foreach (['spreadsheetId', 'serviceAccountJsonPath'] as $requiredField) {
            if (!isset($googleSheetsConfig[$requiredField]) || !is_string($googleSheetsConfig[$requiredField]) || trim($googleSheetsConfig[$requiredField]) === '') {
                throw new InvalidArgumentException(sprintf('Invalid config: writer.config.googleSheets.%s is required for writerType=googleSheets.', $requiredField));
            }
        }
    }

    protected function validateRepositoryConfig(array $repositoryConfig): void
    {
        if (!isset($repositoryConfig['repositoryList']) || !is_array($repositoryConfig['repositoryList'])) {
            throw new InvalidArgumentException('Invalid config: repository.config.repositoryList must be an array.');
        }

        foreach ($repositoryConfig['repositoryList'] as $index => $repository) {
            if (!is_array($repository)) {
                throw new InvalidArgumentException(sprintf('Invalid config: repository.config.repositoryList[%d] must be an array.', $index));
            }

            foreach (['name', 'directory', 'remote', 'branch'] as $requiredField) {
                if (!isset($repository[$requiredField]) || !is_string($repository[$requiredField]) || $repository[$requiredField] === '') {
                    throw new InvalidArgumentException(sprintf('Invalid config: repository.config.repositoryList[%d].%s is required and must be a non-empty string.', $index, $requiredField));
                }
            }
        }
    }

    protected function assertStringInSet(array $config, string $key, array $allowedValues, string $prefix = 'app.config'): void
    {
        if (!isset($config[$key]) || !is_string($config[$key]) || $config[$key] === '') {
            throw new InvalidArgumentException(sprintf('Invalid config: %s.%s is required and must be a non-empty string.', $prefix, $key));
        }

        if (!in_array($config[$key], $allowedValues, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid config: %s.%s must be one of [%s], got "%s".',
                $prefix,
                $key,
                implode(', ', $allowedValues),
                $config[$key]
            ));
        }
    }

    protected function isValidRegex(string $pattern): bool
    {
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            return preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }
    }
}
