<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\ProviderInterface;
use mikehaertl\shellcommand\Command;

class ComposerFull extends ComposerJsonAndLock
{
    protected const COMPOSER_OUTDATED_CMD_COMMAND = 'cd %s; composer outdated --format=json';
    protected const COMMENT_NEWEST_VERSION = "Latest version: %s\n";

    protected function executePerRepository(RepositoryInterface $repository, ProviderInterface $provider, array $projectNamesGrouped): void
    {
        parent::executePerRepository($repository, $provider, $projectNamesGrouped);

        $this->addLatestAvailableVersion($provider->getLocalRepositoryDirectory(), $repository->getProjectName());
    }

    protected function addLatestAvailableVersion(string $repositoryDirectoryPath, string $projectName)
    {
        $command = new Command(sprintf(self::COMPOSER_OUTDATED_CMD_COMMAND, escapeshellarg($repositoryDirectoryPath)));
        $command->execute();
        if (!$command->getExecuted()) {
            return;
        }

        $outdatedPackages = json_decode($command->getOutput(), true);
        if (!is_array($outdatedPackages) || !isset($outdatedPackages['installed']) || !is_array($outdatedPackages['installed'])) {
            return;
        }
        $outdatedPackages = $outdatedPackages['installed'];

        $skippedPackageGroups = $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_REPLACE);
        foreach ($this->parsedData as $packageGroupName => $packageGroup) {
            if (in_array($packageGroupName, array_column($skippedPackageGroups, 'name'))) {
                continue;
            }

            foreach ($packageGroup as $packageName => $packageRow) {
                $outdatedPackageIndex = array_search($packageName, array_column($outdatedPackages, 'name'), true);
                if ($outdatedPackageIndex === false) {
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
