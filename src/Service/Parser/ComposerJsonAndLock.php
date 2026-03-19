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

    protected function executePerRepository(RepositoryInterface $repository, ProviderInterface $provider, array $projectNamesGrouped): RepositoryData
    {
        $repositoryData = parent::executePerRepository($repository, $provider, $projectNamesGrouped);

        if ($this->packageConfig->includeInstalledVersion() && !empty($repositoryData->getComposerLock())) {
            $this->parseComposerLockFile($repositoryData->getComposerLock(), $repository->getProjectName());
            $this->checkObservedPackages($repositoryData->getComposerLock(), $repository->getProjectName());
        }

        return $repositoryData;
    }

    protected function parseComposerLockFile(array $composerLockContent, string $projectName): void
    {
        $skippedPackageGroups = array_merge(
            $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_REPLACE),
            $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_PATCHSET)
        );
        $packagesInstalled = $composerLockContent['packages'] ?? [];
        if ($packagesInstalled === []) {
            return;
        }

        foreach ($this->parsedData as $packageGroupName => $packageGroup) {
            if (in_array($packageGroupName, array_column($skippedPackageGroups, 'name'))) {
                continue;
            }

            foreach ($packageGroup as $packageName => $packageRow) {
                $packageInstalledIndex = array_search($packageName, array_column($packagesInstalled, 'name'), true);
                if ($packageInstalledIndex === false) {
                    continue;
                }

                $packageInstalled = $packagesInstalled[$packageInstalledIndex];
                if ($packageInstalled['version'] == $this->parsedData[$packageGroupName][$packageName][$projectName]['value']) {
                    continue;
                }

                $this->addInstalledVersion($packageGroupName, $packageName, $projectName, $packageInstalled['version']);
            }
        }
    }

    protected function checkObservedPackages(array $composerLockContent, string $projectName): void
    {
        $packageGroups = $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_OBSERVED);
        $observedPackages = $this->packageConfig->getObservedPackages();
        $packagesInstalled = $composerLockContent['packages'];

        foreach ($packageGroups as $packageGroup) {
            $packageGroupName = $packageGroup['name'];
            $matchedPackagesNames = preg_grep($packageGroup['regex'], $observedPackages);

            foreach ($matchedPackagesNames as $matchedPackageName) {
                $packageInstalledIndex = array_search($matchedPackageName, array_column($packagesInstalled, 'name'), true);
                if ($packageInstalledIndex === false) {
                    continue;
                }

                $packageInstalled = $packagesInstalled[$packageInstalledIndex];
                $this->parsedData[$packageGroupName][$matchedPackageName][$projectName]['value'] = '';
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
}
