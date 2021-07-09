<?php

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\ProviderInterface;

class ComposerJsonAndLock extends ComposerJson
{
    /**
     * @param RepositoryInterface $repository
     * @param ProviderInterface $provider
     * @param array $projectNamesGrouped
     */
    protected function executePerRepository(RepositoryInterface $repository, ProviderInterface $provider, array $projectNamesGrouped): void
    {
        parent::executePerRepository($repository, $provider, $projectNamesGrouped);

        $composerLockContent = $provider->getComposerLockContent();
        if ($this->packageConfig->includeInstalledVersion() && !empty($composerLockContent)) {
            $this->parseComposerLockFile($composerLockContent, $repository->getProjectName());
            $this->checkObservedPackages($composerLockContent, $repository->getProjectName());
        }
    }

    /**
     * @param array $composerLockContent
     * @param string $projectName
     */
    protected function parseComposerLockFile(array $composerLockContent, string $projectName): void
    {
        $skippedPackageGroups = $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_REPLACE);
        $packagesInstalled = $composerLockContent['packages'];

        foreach ($this->parsedData as $packageGroupName => $packageGroup) {
            if (array_search($packageGroupName, array_column($skippedPackageGroups, 'name')) !== false) {
                continue;
            }

            foreach ($packageGroup as $packageName => $packageRow) {
                $packageInstalledIndex = array_search($packageName, array_column($packagesInstalled, 'name'));
                if (!$packageInstalledIndex) {
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

    /**
     * @param array $composerLockContent
     * @param string $projectName
     */
    protected function checkObservedPackages(array $composerLockContent, string $projectName): void
    {
        $packageGroups = $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_OBSERVED);
        $observedPackages = $this->packageConfig->getObservedPackages();
        $packagesInstalled = $composerLockContent['packages'];

        foreach ($packageGroups as $packageGroup) {
            $packageGroupName = $packageGroup['name'];
            $matchedPackagesNames = preg_grep($packageGroup['regex'], $observedPackages);

            foreach ($matchedPackagesNames as $matchedPackageName) {
                $packageInstalledIndex = array_search($matchedPackageName, array_column($packagesInstalled, 'name'));
                if (!$packageInstalledIndex) {
                    continue;
                }

                $packageInstalled = $packagesInstalled[$packageInstalledIndex];
                $this->parsedData[$packageGroupName][$matchedPackageName][$projectName]['value'] = '';
                $this->addInstalledVersion($packageGroupName, $matchedPackageName, $projectName, $packageInstalled['version']);
            }
        }
    }

    /**
     * @param $packageGroupName
     * @param $packageName
     * @param $projectName
     * @param $version
     */
    protected function addInstalledVersion($packageGroupName, $packageName, $projectName, $version): void
    {
        switch ($this->packageConfig->installedVersionDisplayedIn()) {
            case PackageConfigInterface::INSTALLED_VERSION_DISPLAYED_IN_VALUE:
                $this->parsedData[$packageGroupName][$packageName][$projectName]['value'] = $version;
                break;
            case PackageConfigInterface::INSTALLED_VERSION_DISPLAYED_IN_COMMENT:
                $comment = sprintf(self::COMMENT_INSTALLED_VERSION, $version);
                $this->parsedData[$packageGroupName][$packageName][$projectName]['comment'] = $comment;
                break;
        }
    }
}