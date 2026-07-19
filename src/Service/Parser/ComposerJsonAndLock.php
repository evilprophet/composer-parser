<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\ProviderInterface;
use EvilStudio\ComposerParser\Model\RepositoryData;

class ComposerJsonAndLock extends ComposerJson
{
    protected const string COMMENT_INSTALLED_VERSION = "Installed version: %s\n";
    protected const string LOCK_PACKAGES_KEY = 'packages';
    protected const string LOCK_PACKAGES_DEV_KEY = 'packages-dev';

    protected function executePerRepository(RepositoryInterface $repository, ProviderInterface $provider, array $projectNamesGrouped): RepositoryData
    {
        $repositoryData = parent::executePerRepository($repository, $provider, $projectNamesGrouped);

        if ($this->packageConfig->includeInstalledVersion() && !empty($repositoryData->getComposerLock())) {
            $installedPackagesByName = $this->getInstalledPackagesByName($repositoryData->getComposerLock());
            $this->addInstalledPackageVersions($installedPackagesByName, $repository->getProjectName());
            $this->addObservedPackageVersions($installedPackagesByName, $repository->getProjectName());
        }

        return $repositoryData;
    }

    protected function addInstalledPackageVersions(array $installedPackagesByName, string $projectName): void
    {
        $skippedPackageGroups = array_merge(
            $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_REPLACE),
            $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_PATCHSET),
            $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_OBSERVED)
        );
        $skippedPackageGroupsByName = array_fill_keys(array_column($skippedPackageGroups, 'name'), true);

        if ($installedPackagesByName === []) {
            return;
        }

        foreach ($this->parsedData as $packageGroupName => $packageGroup) {
            if (isset($skippedPackageGroupsByName[$packageGroupName])) {
                continue;
            }

            foreach ($packageGroup as $packageName => $packageRow) {
                if (!isset($installedPackagesByName[$packageName])) {
                    continue;
                }

                $packageInstalled = $installedPackagesByName[$packageName];
                if ($packageInstalled['version'] == $this->parsedData[$packageGroupName][$packageName][$projectName]['value']) {
                    continue;
                }

                $this->addInstalledVersion($packageGroupName, $packageName, $projectName, $packageInstalled['version']);
            }
        }
    }

    protected function addObservedPackageVersions(array $installedPackagesByName, string $projectName): void
    {
        $packageGroups = $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_OBSERVED);
        $observedPackages = $this->packageConfig->getObservedPackages();

        foreach ($packageGroups as $packageGroup) {
            $packageGroupName = $packageGroup['name'];
            $matchedPackagesNames = preg_grep($packageGroup['regex'], $observedPackages);

            foreach ($matchedPackagesNames as $matchedPackageName) {
                if (!isset($installedPackagesByName[$matchedPackageName])) {
                    continue;
                }

                $packageInstalled = $installedPackagesByName[$matchedPackageName];
                $this->parsedData[$packageGroupName][$matchedPackageName][$projectName] = ['value' => '', 'comment' => ''];
                $this->addInstalledVersion($packageGroupName, $matchedPackageName, $projectName, $packageInstalled['version']);
            }
        }
    }

    protected function addInstalledVersion(string $packageGroupName, string $packageName, string $projectName, string $version): void
    {
        switch ($this->packageConfig->installedVersionDisplayedIn()) {
            case PackageConfigInterface::INSTALLED_VERSION_DISPLAYED_IN_VALUE:
                $this->parsedData[$packageGroupName][$packageName][$projectName]['value'] = $version;
                break;
            case PackageConfigInterface::INSTALLED_VERSION_DISPLAYED_IN_COMMENT:
                $comment = sprintf(self::COMMENT_INSTALLED_VERSION, $version);
                $this->parsedData[$packageGroupName][$packageName][$projectName]['comment'] .= $comment;
                break;
        }
    }

    protected function getInstalledPackagesByName(array $composerLockContent): array
    {
        $installedPackages = array_merge(
            $composerLockContent[self::LOCK_PACKAGES_KEY] ?? [],
            $composerLockContent[self::LOCK_PACKAGES_DEV_KEY] ?? []
        );

        return $this->indexPackagesByName($installedPackages);
    }

    protected function indexPackagesByName(array $packages): array
    {
        return array_column($packages, null, 'name');
    }
}
