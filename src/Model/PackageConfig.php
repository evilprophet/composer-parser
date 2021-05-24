<?php

namespace EvilStudio\ComposerParser\Model;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;

class PackageConfig implements PackageConfigInterface
{
    /**
     * @var array
     */
    protected $packageGroups;

    public function __construct(array $packagesGroups)
    {
        $this->packageGroups = $packagesGroups;
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