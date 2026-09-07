<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Writer\Support;

trait OrdersGroupsByConfig
{
    protected function getOrderedGroups(array $groups): array
    {
        $orderedGroups = [];
        foreach ($this->packageConfig->getPackageGroupsForWriter() as $packageGroup) {
            $groupName = $packageGroup['name'];
            if (!array_key_exists($groupName, $groups)) {
                continue;
            }

            $orderedGroups[$groupName] = $groups[$groupName];
        }

        foreach ($groups as $groupName => $packages) {
            if (array_key_exists($groupName, $orderedGroups)) {
                continue;
            }

            $orderedGroups[$groupName] = $packages;
        }

        return $orderedGroups;
    }
}
