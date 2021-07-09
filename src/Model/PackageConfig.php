<?php

namespace EvilStudio\ComposerParser\Model;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;

class PackageConfig implements PackageConfigInterface
{
    /**
     * @var bool
     */
    protected $includeInstalledVersion;

    /**
     * @var string
     */
    protected $installedVersionDisplayedIn;

    /**
     * @var array
     */
    protected $packageGroups;

    /**
     * @var array
     */
    protected $observedPackages;

    /**
     * PackageConfig constructor.
     * @param array $packageConfigData
     */
    public function __construct(array $packageConfigData)
    {
        $this->includeInstalledVersion = $packageConfigData['includeInstalledVersion'] ?? false;
        $this->installedVersionDisplayedIn = $packageConfigData['installedVersionDisplayedIn'] ?? self::INSTALLED_VERSION_DISPLAYED_IN_COMMENT;
        $this->packageGroups = $packageConfigData['packageGroups'] ?? [];
        $this->observedPackages = $packageConfigData['observedPackages'] ?? [];
    }

    /**
     * @return bool
     */
    public function includeInstalledVersion(): bool
    {
        return $this->includeInstalledVersion;
    }

    /**
     * @return string
     */
    public function installedVersionDisplayedIn(): string
    {
        return $this->installedVersionDisplayedIn;
    }

    /**
     * @param string|null $groupType
     * @return array
     */
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

    /**
     * @return array
     */
    public function getPackageGroupsForWriter(): array
    {
        $writerPackageGroup = $this->packageGroups;
        usort($writerPackageGroup, function ($a, $b) {
            return $a['writerOrder'] > $b['writerOrder'] ? 1 : -1;
        });

        return $writerPackageGroup;
    }

    /**
     * @return array
     */
    public function getObservedPackages(): array
    {
        sort($this->observedPackages);

        return $this->observedPackages;
    }
}