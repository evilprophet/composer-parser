<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Config;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Service\Repository\RepositoryDirectoryPath;
use InvalidArgumentException;

class ConfigValidator
{
    protected const array ALLOWED_PROVIDER_TYPES = ['gitRepository', 'gitlabApiFiles', 'gitlabApiArchive'];
    protected const array GITLAB_PROVIDER_TYPES = ['gitlabApiFiles', 'gitlabApiArchive'];
    protected const array ALLOWED_PARSER_TYPES = ['composerJson', 'composerJsonAndLock', 'composerFull'];
    protected const array PARSER_TYPES_WITH_COMPOSER_LOCK = ['composerJsonAndLock', 'composerFull'];
    protected const array ALLOWED_WRITER_TYPES = ['xlsx', 'json', 'html', 'googleSheets'];
    protected const array STYLED_WRITER_TYPES = ['xlsx', 'html', 'googleSheets'];
    protected const array XLSX_INVALID_SHEET_NAME_CHARACTERS = ['*', ':', '/', '\\', '?', '[', ']'];
    protected const array ALLOWED_INSTALLED_VERSION_DISPLAY = ['value', 'comment'];
    protected const string HEX_COLOR_REGEX = '/^#[0-9A-Fa-f]{6}$/';
    protected const array ALLOWED_PACKAGE_GROUP_TYPES = [
        PackageConfigInterface::COMPOSER_TYPE_REQUIRE,
        PackageConfigInterface::COMPOSER_TYPE_REQUIRE_DEV,
        PackageConfigInterface::COMPOSER_TYPE_REPLACE,
        PackageConfigInterface::COMPOSER_TYPE_PATCHSET,
        PackageConfigInterface::COMPOSER_TYPE_OBSERVED,
    ];

    public function validate(array $appConfig, array $packageConfig, array $writerConfig, array $repositoryConfig): void
    {
        $this->validateAppConfig($appConfig);
        $this->validatePackageConfig($packageConfig, $appConfig['parserType']);
        $this->validateWriterConfig($writerConfig, $appConfig['writerType']);
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

        if (!in_array($appConfig['providerType'], self::GITLAB_PROVIDER_TYPES, true)) {
            return;
        }

        if (!isset($appConfig['gitlab']) || !is_array($appConfig['gitlab'])) {
            throw new InvalidArgumentException('Invalid config: app.config.gitlab must be an array for GitLab providers.');
        }

        foreach (['url', 'apiToken'] as $requiredField) {
            if (!isset($appConfig['gitlab'][$requiredField]) || !is_string($appConfig['gitlab'][$requiredField]) || trim($appConfig['gitlab'][$requiredField]) === '') {
                throw new InvalidArgumentException(sprintf(
                    'Invalid config: app.config.gitlab.%s is required for providerType=%s.',
                    $requiredField,
                    $appConfig['providerType']
                ));
            }
        }
    }

    protected function validatePackageConfig(array $packageConfig, string $parserType): void
    {
        if (!array_key_exists('includeInstalledVersion', $packageConfig) || !is_bool($packageConfig['includeInstalledVersion'])) {
            throw new InvalidArgumentException('Invalid config: package.config.includeInstalledVersion must be a boolean.');
        }

        $this->assertStringInSet($packageConfig, 'installedVersionDisplayedIn', self::ALLOWED_INSTALLED_VERSION_DISPLAY, 'package.config');

        if (!isset($packageConfig['packageGroups']) || !is_array($packageConfig['packageGroups'])) {
            throw new InvalidArgumentException('Invalid config: package.config.packageGroups must be an array.');
        }

        $hasObservedPackageGroup = false;
        foreach ($packageConfig['packageGroups'] as $index => $group) {
            if (!is_array($group)) {
                throw new InvalidArgumentException(sprintf('Invalid config: package.config.packageGroups[%d] must be an array.', $index));
            }

            foreach (['name', 'groupType', 'regex'] as $requiredField) {
                if (!isset($group[$requiredField]) || !is_string($group[$requiredField]) || $group[$requiredField] === '') {
                    throw new InvalidArgumentException(sprintf('Invalid config: package.config.packageGroups[%d].%s is required and must be a non-empty string.', $index, $requiredField));
                }
            }

            $groupConfigPrefix = sprintf('package.config.packageGroups[%d]', $index);
            $this->assertInteger($group, 'parserPriority', $groupConfigPrefix);
            $this->assertInteger($group, 'writerOrder', $groupConfigPrefix);
            $this->assertStringInSet($group, 'groupType', self::ALLOWED_PACKAGE_GROUP_TYPES, $groupConfigPrefix);
            $hasObservedPackageGroup = $hasObservedPackageGroup
                || $group['groupType'] === PackageConfigInterface::COMPOSER_TYPE_OBSERVED;

            if (!$this->isValidRegex($group['regex'])) {
                throw new InvalidArgumentException(sprintf('Invalid config: package.config.packageGroups[%d].regex is not a valid regex.', $index));
            }
        }

        $observedPackages = $packageConfig['observedPackages'] ?? [];
        if (!is_array($observedPackages)) {
            throw new InvalidArgumentException('Invalid config: package.config.observedPackages must be an array.');
        }

        foreach ($observedPackages as $index => $packageName) {
            if (!is_string($packageName) || trim($packageName) === '') {
                throw new InvalidArgumentException(sprintf('Invalid config: package.config.observedPackages[%s] must be a non-empty string.', (string) $index));
            }
        }

        if (!$hasObservedPackageGroup) {
            return;
        }

        if (!in_array($parserType, self::PARSER_TYPES_WITH_COMPOSER_LOCK, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid config: package groupType=observed is not supported by parserType=%s.',
                $parserType
            ));
        }

        if (!$packageConfig['includeInstalledVersion']) {
            throw new InvalidArgumentException(
                'Invalid config: package groupType=observed requires package.config.includeInstalledVersion=true.'
            );
        }

        if ($observedPackages === []) {
            throw new InvalidArgumentException(
                'Invalid config: package groupType=observed requires at least one package.config.observedPackages entry.'
            );
        }
    }

    protected function validateWriterConfig(array $writerConfig, string $writerType): void
    {
        if (!isset($writerConfig['local']) || !is_array($writerConfig['local'])) {
            throw new InvalidArgumentException('Invalid config: writer.config.local must be an array.');
        }

        foreach (['fileName', 'fileDirectory'] as $requiredField) {
            if (!isset($writerConfig['local'][$requiredField]) || !is_string($writerConfig['local'][$requiredField]) || $writerConfig['local'][$requiredField] === '') {
                throw new InvalidArgumentException(sprintf('Invalid config: writer.config.local.%s is required and must be a non-empty string.', $requiredField));
            }
        }

        if (!isset($writerConfig['shared']) || !is_array($writerConfig['shared'])) {
            throw new InvalidArgumentException('Invalid config: writer.config.shared must be an array.');
        }

        if (!isset($writerConfig['shared']['sheetName']) || !is_string($writerConfig['shared']['sheetName']) || trim($writerConfig['shared']['sheetName']) === '') {
            throw new InvalidArgumentException('Invalid config: writer.config.shared.sheetName is required and must be a non-empty string.');
        }

        $sheetName = $writerConfig['shared']['sheetName'];
        if (
            $writerType === 'xlsx'
            && str_replace(self::XLSX_INVALID_SHEET_NAME_CHARACTERS, '', $sheetName) !== $sheetName
        ) {
            throw new InvalidArgumentException(sprintf(
                'Invalid config: writer.config.shared.sheetName cannot contain %s for writerType=xlsx.',
                implode(' ', self::XLSX_INVALID_SHEET_NAME_CHARACTERS)
            ));
        }

        if (isset($writerConfig['googleSheets']) && !is_array($writerConfig['googleSheets'])) {
            throw new InvalidArgumentException('Invalid config: writer.config.googleSheets must be an array.');
        }

        if (in_array($writerType, self::STYLED_WRITER_TYPES, true)) {
            $this->validateStylingConfig($writerConfig);
        }
    }

    protected function validateStylingConfig(array $writerConfig): void
    {
        if (!isset($writerConfig['styling']) || !is_array($writerConfig['styling'])) {
            throw new InvalidArgumentException('Invalid config: writer.config.styling must be an array.');
        }

        $stylingConfig = $writerConfig['styling'];
        if (
            !isset($stylingConfig['groupHeaderBackgroundColor'])
            || !is_string($stylingConfig['groupHeaderBackgroundColor'])
            || preg_match(self::HEX_COLOR_REGEX, $stylingConfig['groupHeaderBackgroundColor']) !== 1
        ) {
            throw new InvalidArgumentException('Invalid config: writer.config.styling.groupHeaderBackgroundColor must be a #RRGGBB string.');
        }

        if (!isset($stylingConfig['cellStyleMapping']) || !is_array($stylingConfig['cellStyleMapping'])) {
            throw new InvalidArgumentException('Invalid config: writer.config.styling.cellStyleMapping must be an array.');
        }

        foreach ($stylingConfig['cellStyleMapping'] as $index => $cellStyle) {
            if (!is_array($cellStyle)) {
                throw new InvalidArgumentException(sprintf('Invalid config: writer.config.styling.cellStyleMapping[%d] must be an array.', $index));
            }

            $this->assertStyleRegex($cellStyle, 'versionRegex', $index, true);
            $this->assertStyleRegex($cellStyle, 'packageNameRegex', $index, false);
            $this->assertStyleHexColor($cellStyle, 'color', $index);
            $this->assertStyleHexColor($cellStyle, 'backgroundColor', $index);
        }
    }

    protected function assertStyleRegex(array $cellStyle, string $field, int $index, bool $required): void
    {
        if (!$required && !array_key_exists($field, $cellStyle)) {
            return;
        }

        if (!isset($cellStyle[$field]) || !is_string($cellStyle[$field]) || trim($cellStyle[$field]) === '') {
            throw new InvalidArgumentException(sprintf(
                'Invalid config: writer.config.styling.cellStyleMapping[%d].%s must be a non-empty string.',
                $index,
                $field
            ));
        }

        if (!$this->isValidRegex($cellStyle[$field])) {
            throw new InvalidArgumentException(sprintf(
                'Invalid config: writer.config.styling.cellStyleMapping[%d].%s is not a valid regex.',
                $index,
                $field
            ));
        }
    }

    protected function assertStyleHexColor(array $cellStyle, string $field, int $index): void
    {
        if (!array_key_exists($field, $cellStyle)) {
            return;
        }

        if (!is_string($cellStyle[$field]) || preg_match(self::HEX_COLOR_REGEX, $cellStyle[$field]) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid config: writer.config.styling.cellStyleMapping[%d].%s must be a #RRGGBB string.',
                $index,
                $field
            ));
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

    public function validateRepositoryConfig(array $repositoryConfig): void
    {
        if (!isset($repositoryConfig['repositoryList']) || !is_array($repositoryConfig['repositoryList'])) {
            throw new InvalidArgumentException('Invalid config: repository.config.repositoryList must be an array.');
        }

        $repositoryNames = [];
        $repositoryDirectories = [];
        foreach ($repositoryConfig['repositoryList'] as $index => $repository) {
            if (!is_array($repository)) {
                throw new InvalidArgumentException(sprintf('Invalid config: repository.config.repositoryList[%d] must be an array.', $index));
            }

            foreach (['name', 'directory', 'remote', 'branch'] as $requiredField) {
                if (!isset($repository[$requiredField]) || !is_string($repository[$requiredField]) || $repository[$requiredField] === '') {
                    throw new InvalidArgumentException(sprintf('Invalid config: repository.config.repositoryList[%d].%s is required and must be a non-empty string.', $index, $requiredField));
                }
            }

            if ((string) (int) $repository['name'] === $repository['name']) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid config: repository.config.repositoryList[%d].name must not be an integer-like string.',
                    $index
                ));
            }

            try {
                $resolvedDirectory = RepositoryDirectoryPath::resolve('/app', $repository['directory']);
            } catch (InvalidArgumentException $exception) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid config: repository.config.repositoryList[%d].directory is unsafe. %s',
                    $index,
                    $exception->getMessage()
                ), 0, $exception);
            }

            $this->assertUniqueRepositoryValue($repositoryNames, $repository['name'], $repository['name'], 'name', $index);
            $this->assertUniqueRepositoryValue($repositoryDirectories, $resolvedDirectory, $repository['directory'], 'directory', $index);
        }
    }

    protected function assertInteger(array $config, string $key, string $prefix): void
    {
        if (!array_key_exists($key, $config) || !is_int($config[$key])) {
            throw new InvalidArgumentException(sprintf('Invalid config: %s.%s is required and must be an integer.', $prefix, $key));
        }
    }

    protected function assertUniqueRepositoryValue(array &$seenValues, string $key, string $displayValue, string $field, int $index): void
    {
        if (isset($seenValues[$key])) {
            throw new InvalidArgumentException(sprintf(
                'Invalid config: repository.config.repositoryList[%d].%s "%s" duplicates entry %d.',
                $index,
                $field,
                $displayValue,
                $seenValues[$key]
            ));
        }

        $seenValues[$key] = $index;
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
