<?php

namespace EvilStudio\ComposerParser\Api\Data;

interface PackageConfigInterface
{
    public const COMPOSER_TYPE_REQUIRE = 'require';
    public const COMPOSER_TYPE_REPLACE = 'replace';
    public const COMPOSER_TYPE_PATCHSET = 'patchset';
    public const COMPOSER_TYPE_OBSERVED = 'observed';

    public const INSTALLED_VERSION_DISPLAYED_IN_COMMENT = 'comment';
    public const INSTALLED_VERSION_DISPLAYED_IN_VALUE = 'value';

    public function includeInstalledVersion(): bool;

    public function installedVersionDisplayedIn(): string;

    public function getPackageGroupsForParser(?string $groupType): array;

    public function getPackageGroupsForWriter(): array;

    public function getObservedPackages(): array;
}