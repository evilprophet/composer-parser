<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Exception\RepositoryProcessingException;
use EvilStudio\ComposerParser\Model\ParsedData;
use JsonException;
use mikehaertl\shellcommand\Command;
use RuntimeException;
use Throwable;

class ComposerFull extends ComposerJsonAndLock
{
    protected const string COMPOSER_OUTDATED_COMMAND = 'composer outdated --no-plugins --no-scripts --format=json';
    protected const string COMMENT_NEWEST_VERSION = "Latest version: %s\n";
    protected const string COMMAND_EXECUTION_ERROR = 'Unable to inspect outdated packages in "%s": %s';
    protected const string INVALID_COMMAND_OUTPUT_ERROR = 'Composer returned invalid outdated package data for "%s": %s';

    public function execute(): ParsedDataInterface
    {
        $parsedData = parent::execute();
        $projectNames = $parsedData->getProjectNames();
        unset($parsedData);

        $this->addLatestAvailableVersionsForRepositories();

        return new ParsedData($this->parsedData, $projectNames);
    }

    protected function addLatestAvailableVersionsForRepositories(): void
    {
        $provider = $this->providerManager->getProvider();
        foreach ($this->repositoryList->getList() as $repository) {
            $projectName = $repository->getProjectName();

            try {
                $repositoryDirectoryPath = $provider->getLocalRepositoryDirectoryForRepository($repository);
                $outdatedPackageDataByName = $this->getOutdatedPackageDataByName($repositoryDirectoryPath);
                $this->addLatestAvailableVersions($outdatedPackageDataByName, $projectName);
            } catch (Throwable $throwable) {
                throw new RepositoryProcessingException($projectName, $throwable);
            } finally {
                unset($outdatedPackageDataByName);
            }
        }
    }

    protected function getOutdatedPackageDataByName(string $repositoryDirectoryPath): array
    {
        $command = $this->createComposerOutdatedCommand($repositoryDirectoryPath);
        if (!$command->execute()) {
            throw new RuntimeException(sprintf(
                self::COMMAND_EXECUTION_ERROR,
                $repositoryDirectoryPath,
                $command->getError()
            ));
        }

        try {
            $outdatedPackages = json_decode($command->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf(
                self::INVALID_COMMAND_OUTPUT_ERROR,
                $repositoryDirectoryPath,
                $exception->getMessage()
            ), 0, $exception);
        }

        if (!is_array($outdatedPackages) || !isset($outdatedPackages['installed']) || !is_array($outdatedPackages['installed'])) {
            throw new RuntimeException(sprintf(
                self::INVALID_COMMAND_OUTPUT_ERROR,
                $repositoryDirectoryPath,
                'the "installed" package list is missing'
            ));
        }

        $this->validateOutdatedPackages($outdatedPackages['installed'], $repositoryDirectoryPath);

        $outdatedPackageDataByName = [];
        foreach ($outdatedPackages['installed'] as $outdatedPackage) {
            $outdatedPackageDataByName[$outdatedPackage['name']] = [
                'version' => $outdatedPackage['version'],
                'latest' => $outdatedPackage['latest'],
                'latest-status' => $outdatedPackage['latest-status'],
            ];
        }

        return $outdatedPackageDataByName;
    }

    protected function addLatestAvailableVersions(array $outdatedPackageDataByName, string $projectName): void
    {
        $skippedPackageGroups = $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_REPLACE);
        $skippedPackageGroupsByName = array_fill_keys(array_column($skippedPackageGroups, 'name'), true);
        foreach ($this->parsedData as $packageGroupName => $packageGroup) {
            if (isset($skippedPackageGroupsByName[$packageGroupName])) {
                continue;
            }

            foreach ($packageGroup as $packageName => $packageRow) {
                if (!isset($outdatedPackageDataByName[$packageName])) {
                    continue;
                }

                $outdatedPackage = $outdatedPackageDataByName[$packageName];
                if ($outdatedPackage['latest-status'] == 'up-to-date' || $outdatedPackage['latest'] == $outdatedPackage['version']) {
                    continue;
                }

                $comment = sprintf(self::COMMENT_NEWEST_VERSION, $outdatedPackage['latest']);
                $this->parsedData[$packageGroupName][$packageName][$projectName]['comment'] .= $comment;
            }
        }
    }

    protected function createComposerOutdatedCommand(string $repositoryDirectoryPath): Command
    {
        return new Command([
            'command' => self::COMPOSER_OUTDATED_COMMAND,
            'procCwd' => $repositoryDirectoryPath,
        ]);
    }

    protected function validateOutdatedPackages(array $outdatedPackages, string $repositoryDirectoryPath): void
    {
        foreach ($outdatedPackages as $index => $outdatedPackage) {
            if (!is_array($outdatedPackage)) {
                throw new RuntimeException(sprintf(
                    self::INVALID_COMMAND_OUTPUT_ERROR,
                    $repositoryDirectoryPath,
                    sprintf('package at index %s must be an object', (string) $index)
                ));
            }

            foreach (['name', 'version', 'latest', 'latest-status'] as $requiredField) {
                if (!isset($outdatedPackage[$requiredField]) || !is_string($outdatedPackage[$requiredField])) {
                    throw new RuntimeException(sprintf(
                        self::INVALID_COMMAND_OUTPUT_ERROR,
                        $repositoryDirectoryPath,
                        sprintf('package at index %s requires string field "%s"', (string) $index, $requiredField)
                    ));
                }
            }
        }
    }
}
