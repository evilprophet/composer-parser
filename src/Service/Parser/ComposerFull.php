<?php

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\ProviderInterface;
use mikehaertl\shellcommand\Command;

class ComposerFull extends ComposerJsonAndLock
{
    const COMPOSER_OUTDATED_CMD_COMMAND = 'cd %s; composer outdated --format=json';
    const COMMENT_NEWEST_VERSION = "Latest version: %s\n";

    /**
     * @param RepositoryInterface $repository
     * @param ProviderInterface $provider
     * @param array $projectNamesGrouped
     */
    protected function executePerRepository(RepositoryInterface $repository, ProviderInterface $provider, array $projectNamesGrouped): void
    {
        parent::executePerRepository($repository, $provider, $projectNamesGrouped);

        $this->addLatestAvailableVersion($provider->getLocalRepositoryDirectory(), $repository->getProjectName());
    }

    /**
     * @param string $repositoryDirectoryPath
     * @param string $projectName
     */
    protected function addLatestAvailableVersion(string $repositoryDirectoryPath, string $projectName)
    {
        $command = new Command(sprintf(self::COMPOSER_OUTDATED_CMD_COMMAND, $repositoryDirectoryPath));
        $command->execute();
        if (!$command->getExecuted()) {
            return;
        }

        $outdatedPackages = json_decode($command->getOutput(), true);
        $outdatedPackages = $outdatedPackages['installed'];

        $skippedPackageGroups = $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_REPLACE);
        foreach ($this->parsedData as $packageGroupName => $packageGroup) {
            if (in_array($packageGroupName, array_column($skippedPackageGroups, 'name'))) {
                continue;
            }

            foreach ($packageGroup as $packageName => $packageRow) {
                $outdatedPackageIndex = array_search($packageName, array_column($outdatedPackages, 'name'));
                if (!$outdatedPackageIndex) {
                    continue;
                }

                $outdatedPackage = $outdatedPackages[$outdatedPackageIndex];
                if ($outdatedPackage['latest-status'] == 'up-to-date' || $outdatedPackage['latest'] == $outdatedPackage['version']) {
                    continue;
                }

                $comment = sprintf(self::COMMENT_NEWEST_VERSION, $outdatedPackage['latest']);
                $this->parsedData[$packageGroupName][$packageName][$projectName]['comment'] .= $comment;
            }
        }
    }
}