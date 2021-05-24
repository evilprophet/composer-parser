<?php

namespace EvilStudio\ComposerParser\Api\Data;

interface PackageConfigInterface
{
    const COMPOSER_TYPE_REQUIRE = 'require';
    const COMPOSER_TYPE_REPLACE = 'replace';

    /**
     * @param string|null $section
     * @return array
     */
    public function getPackageGroupsForParser(?string $section): array;

    /**
     * @return array
     */
    public function getPackageGroupsForWriter(): array;

    /**
     * @return bool
     */
    public function includeInstalledVersion(): bool;
}