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

    protected array $outdatedPackagesByProject = [];

    public function execute(): ParsedDataInterface
    {
        $this->outdatedPackagesByProject = [];
        $parsedData = parent::execute();

        $this->addCollectedLatestAvailableVersions();

        return new ParsedData($this->parsedData, $parsedData->getProjectNames());
    }

    protected function addCollectedLatestAvailableVersions(): void
    {
        foreach ($this->outdatedPackagesByProject as $projectName => $outdatedPackagesByName) {
            try {
                $this->addLatestAvailableVersions($outdatedPackagesByName, $projectName);
            } catch (Throwable $throwable) {
                throw new RepositoryProcessingException($projectName, $throwable);
            }
        }
    }

    protected function executePerRepository(RepositoryInterface $repository, ProviderInterface $provider, array $projectNamesGrouped): RepositoryData
    {
        $repositoryData = parent::executePerRepository($repository, $provider, $projectNamesGrouped);

        $this->outdatedPackagesByProject[$repository->getProjectName()] = $this->getOutdatedPackagesByName($provider->getLocalRepositoryDirectory());

        return $repositoryData;
    }

    protected function getOutdatedPackagesByName(string $repositoryDirectoryPath): array
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

        return $this->indexPackagesByName($outdatedPackages['installed']);
    }

    protected function addLatestAvailableVersions(array $outdatedPackagesByName, string $projectName): void
    {
        $skippedPackageGroups = $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_REPLACE);
        $skippedPackageGroupsByName = array_fill_keys(array_column($skippedPackageGroups, 'name'), true);
        foreach ($this->parsedData as $packageGroupName => $packageGroup) {
            if (isset($skippedPackageGroupsByName[$packageGroupName])) {
                continue;
            }

            foreach ($packageGroup as $packageName => $packageRow) {
                if (!isset($outdatedPackagesByName[$packageName])) {
                    continue;
                }

                $outdatedPackage = $outdatedPackagesByName[$packageName];
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
