<?php

namespace EvilStudio\ComposerParser\Model;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;

class PackageConfig implements PackageConfigInterface
{
    protected bool $includeInstalledVersion;

    protected string $installedVersionDisplayedIn;

    protected array $packageGroups;

    protected array $observedPackages;

    public function __construct(array $packageConfigData)
    {
        $this->includeInstalledVersion = $packageConfigData['includeInstalledVersion'] ?? false;
        $this->installedVersionDisplayedIn = $packageConfigData['installedVersionDisplayedIn'] ?? self::INSTALLED_VERSION_DISPLAYED_IN_COMMENT;
        $this->packageGroups = $packageConfigData['packageGroups'] ?? [];
        $this->observedPackages = $packageConfigData['observedPackages'] ?? [];
    }

    public function includeInstalledVersion(): bool
    {
        return $this->includeInstalledVersion;
    }

    public function installedVersionDisplayedIn(): string
    {
        return $this->installedVersionDisplayedIn;
    }

    public function getPackageGroupsForParser(?string $groupType): array
    {
        $parserPackageGroup = $this->packageGroups;
        usort($parserPackageGroup, function ($a, $b) {
            return $a['parserPriority'] < $b['parserPriority'] ? 1 : -1;
        });

        if (empty($groupType)) {
            return $parserPackageGroup;
        }

        return array_filter($parserPackageGroup, function ($item) use ($groupType) {
            return $item['groupType'] == $groupType;
        });
    }

    public function getPackageGroupsForWriter(): array
    {
        $writerPackageGroup = $this->packageGroups;
        usort($writerPackageGroup, function ($a, $b) {
            return $a['writerOrder'] > $b['writerOrder'] ? 1 : -1;
        });

        return $writerPackageGroup;
    }

    public function getObservedPackages(): array
    {
        sort($this->observedPackages);

        return $this->observedPackages;
    }
}