<?php

namespace EvilStudio\ComposerParser\Api\Data;

interface PackageConfigInterface
{
    const COMPOSER_TYPE_REQUIRE = 'require';
    const COMPOSER_TYPE_REPLACE = 'replace';

    const INSTALLED_VERSION_DISPLAYED_IN_COMMENT = 'comment';
    const INSTALLED_VERSION_DISPLAYED_IN_VALUE = 'value';

    /**
     * @return bool
     */
    public function includeInstalledVersion(): bool;

    /**
     * @return string
     */
    public function getInstalledVersionDisplayedIn(): string;

    /**
     * @param string|null $section
     * @return array
     */
    public function getPackageGroupsForParser(?string $section): array;

    /**
     * @return array
     */
    public function getPackageGroupsForWriter(): array;
}