<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\ProviderInterface;
use EvilStudio\ComposerParser\Exception\RepositoryProcessingException;
use EvilStudio\ComposerParser\Model\ParsedData;
use EvilStudio\ComposerParser\Model\RepositoryData;
use Throwable;

class ComposerJsonAndLock extends ComposerJson
{
    protected const string COMMENT_INSTALLED_VERSION = "Installed version: %s\n";
    protected const string LOCK_PACKAGES_KEY = 'packages';
    protected const string LOCK_PACKAGES_DEV_KEY = 'packages-dev';

    protected array $installedPackageVersionsByProject = [];

    public function execute(): ParsedDataInterface
    {
        $this->installedPackageVersionsByProject = [];
        $parsedData = parent::execute();

        $this->addCollectedInstalledPackageVersions();

        return new ParsedData($this->parsedData, $parsedData->getProjectNames());
    }

    protected function addCollectedInstalledPackageVersions(): void
    {
        foreach ($this->installedPackageVersionsByProject as $projectName => $installedPackageVersionsByName) {
            try {
                $this->addInstalledPackageVersions($installedPackageVersionsByName, $projectName);
                $this->addObservedPackageVersions($installedPackageVersionsByName, $projectName);
            } catch (Throwable $throwable) {
                throw new RepositoryProcessingException($projectName, $throwable);
            }
        }
    }

    protected function executePerRepository(RepositoryInterface $repository, ProviderInterface $provider, array $projectNamesGrouped): RepositoryData
    {
        $repositoryData = parent::executePerRepository($repository, $provider, $projectNamesGrouped);

        if ($this->packageConfig->includeInstalledVersion() && !empty($repositoryData->getComposerLock())) {
            $this->installedPackageVersionsByProject[$repository->getProjectName()] = $this->getInstalledPackageVersionsByName($repositoryData->getComposerLock());
        }

        return $repositoryData;
    }

    protected function addInstalledPackageVersions(array $installedPackageVersionsByName, string $projectName): void
    {
        $skippedPackageGroups = array_merge(
            $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_REPLACE),
            $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_PATCHSET),
            $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_OBSERVED)
        );
        $skippedPackageGroupsByName = array_fill_keys(array_column($skippedPackageGroups, 'name'), true);

        if ($installedPackageVersionsByName === []) {
            return;
        }

        foreach ($this->parsedData as $packageGroupName => $packageGroup) {
            if (isset($skippedPackageGroupsByName[$packageGroupName])) {
                continue;
            }

            foreach ($packageGroup as $packageName => $packageRow) {
                if (!isset($installedPackageVersionsByName[$packageName])) {
                    continue;
                }

                $installedVersion = $installedPackageVersionsByName[$packageName];
                if ($installedVersion == $this->parsedData[$packageGroupName][$packageName][$projectName]['value']) {
                    continue;
                }

                $this->addInstalledVersion($packageGroupName, $packageName, $projectName, $installedVersion);
            }
        }
    }

    protected function addObservedPackageVersions(array $installedPackageVersionsByName, string $projectName): void
    {
        $packageGroups = $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_OBSERVED);
        $observedPackages = $this->packageConfig->getObservedPackages();

        foreach ($packageGroups as $packageGroup) {
            $packageGroupName = $packageGroup['name'];
            $matchedPackagesNames = preg_grep($packageGroup['regex'], $observedPackages);

            foreach ($matchedPackagesNames as $matchedPackageName) {
                if (!isset($installedPackageVersionsByName[$matchedPackageName])) {
                    continue;
                }

                $installedVersion = $installedPackageVersionsByName[$matchedPackageName];
                $this->parsedData[$packageGroupName][$matchedPackageName][$projectName] = ['value' => '', 'comment' => ''];
                $this->addInstalledVersion($packageGroupName, $matchedPackageName, $projectName, $installedVersion);
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

    protected function getInstalledPackageVersionsByName(array $composerLockContent): array
    {
        $installedPackageVersionsByName = [];

        foreach ([self::LOCK_PACKAGES_KEY, self::LOCK_PACKAGES_DEV_KEY] as $packageListKey) {
            foreach ($composerLockContent[$packageListKey] ?? [] as $installedPackage) {
                $installedPackageVersionsByName[$installedPackage['name']] = $installedPackage['version'];
            }
        }

        return $installedPackageVersionsByName;
    }
}
