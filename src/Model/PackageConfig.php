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
     * PackageConfig constructor.
     * @param bool $includeInstalledVersion
     * @param string $installedVersionDisplayedIn
     * @param array $packageGroups
     */
    public function __construct(bool $includeInstalledVersion, string $installedVersionDisplayedIn, array $packageGroups)
    {
        $this->includeInstalledVersion = $includeInstalledVersion;
        $this->installedVersionDisplayedIn = $installedVersionDisplayedIn;
        $this->packageGroups = $packageGroups;
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
    public function getInstalledVersionDisplayedIn(): string
    {
        return $this->installedVersionDisplayedIn;
    }

    /**
     * @param string|null $section
     * @return array
     */
    public function getPackageGroupsForParser(?string $section): array
    {
        $parserPackageGroup = $this->packageGroups;
        usort($parserPackageGroup, function ($a, $b) {
            return $a['parserPriority'] < $b['parserPriority'] ? 1 : -1;
        });

        if (empty($section)) {
            return $parserPackageGroup;
        }

        return array_filter($parserPackageGroup, function ($item) use ($section) {
            return $item['section'] == $section;
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
}