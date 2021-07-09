<?php

namespace EvilStudio\ComposerParser\Api\Data;

interface PackageConfigInterface
{
    const COMPOSER_TYPE_REQUIRE = 'require';
    const COMPOSER_TYPE_REPLACE = 'replace';
    const COMPOSER_TYPE_OBSERVED = 'observed';

    const INSTALLED_VERSION_DISPLAYED_IN_COMMENT = 'comment';
    const INSTALLED_VERSION_DISPLAYED_IN_VALUE = 'value';

    /**
     * @return bool
     */
    public function includeInstalledVersion(): bool;

    /**
     * @return string
     */
    public function installedVersionDisplayedIn(): string;

    /**
     * @param string|null $groupType
     * @return array
     */
    public function getPackageGroupsForParser(?string $groupType): array;

    /**
     * @return array
     */
    public function getPackageGroupsForWriter(): array;

    /**
     * @return array
     */
    public function getObservedPackages(): array;
}