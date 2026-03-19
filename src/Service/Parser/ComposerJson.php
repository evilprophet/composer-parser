<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryListInterface;
use EvilStudio\ComposerParser\Api\ParserInterface;
use EvilStudio\ComposerParser\Api\ProviderInterface;
use EvilStudio\ComposerParser\Model\ParsedData;
use EvilStudio\ComposerParser\Model\RepositoryData;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;
use EvilStudio\ComposerParser\Service\Parser\RepositoryDataFactory;

class ComposerJson implements ParserInterface
{
    protected array $parsedData = [];

    public function __construct(
        protected PackageConfigInterface $packageConfig,
        protected RepositoryListInterface $repositoryList,
        protected ProviderManager $providerManager,
        protected RepositoryDataFactory $repositoryDataFactory
    ) {}

    public function execute(): ParsedDataInterface
    {
        $this->parsedData = [];

        $provider = $this->providerManager->getProvider();
        $projectNames = $this->repositoryList->getProjectNames();
        $projectNamesGrouped = array_fill_keys($projectNames, ['value' => '', 'comment' => '']);

        foreach ($this->repositoryList->getList() as $repository) {
            $this->executePerRepository($repository, $provider, $projectNamesGrouped);
        }

        return new ParsedData($this->parsedData, $projectNames);
    }

    protected function executePerRepository(RepositoryInterface $repository, ProviderInterface $provider, array $projectNamesGrouped): RepositoryData
    {
        $provider->load($repository);

        $repositoryData = $this->repositoryDataFactory->create(
            $provider->getComposerJsonContent(),
            $provider->getComposerLockContent()
        );

        $this->parseComposerJsonFile($repositoryData->getComposerJson(), $projectNamesGrouped, $repository->getProjectName());
        $this->parsePatchSet($repositoryData->getComposerJson(), $projectNamesGrouped, $repository->getProjectName());

        return $repositoryData;
    }

    protected function parseComposerJsonFile(array $composerJsonContent, array $projectNamesGrouped, string $projectName): void
    {
        $requireGroup = $composerJsonContent[PackageConfigInterface::COMPOSER_TYPE_REQUIRE] ?? [];
        $requireDevGroup = $composerJsonContent[PackageConfigInterface::COMPOSER_TYPE_REQUIRE_DEV] ?? [];
        $replaceGroup = $composerJsonContent[PackageConfigInterface::COMPOSER_TYPE_REPLACE] ?? [];

        $this->parseGroup($requireGroup, $projectName, PackageConfigInterface::COMPOSER_TYPE_REQUIRE);
        $this->parseGroup($requireDevGroup, $projectName, PackageConfigInterface::COMPOSER_TYPE_REQUIRE_DEV);
        $this->parseGroup($replaceGroup, $projectName, PackageConfigInterface::COMPOSER_TYPE_REPLACE);

        foreach ($this->parsedData as &$group) {
            ksort($group);
            foreach ($group as &$item) {
                $item = array_merge($projectNamesGrouped, $item);
            }
        }
    }

    protected function parseGroup(array $group, string $projectName, string $groupType): void
    {
        $packageGroups = $this->packageConfig->getPackageGroupsForParser($groupType);

        foreach ($packageGroups as $packageGroup) {
            if (!key_exists($packageGroup['name'], $this->parsedData)) {
                $this->parsedData[$packageGroup['name']] = [];
            }

            $matchedPackagesNames = preg_grep($packageGroup['regex'], array_keys($group));
            foreach ($matchedPackagesNames as $matchedPackageName) {
                $this->parsedData[$packageGroup['name']][$matchedPackageName][$projectName] = ['value' => $group[$matchedPackageName], 'comment' => ''];
                unset($group[$matchedPackageName]);
            }
        }
    }

    protected function parsePatchSet(array $composerJsonContent, array $projectNamesGrouped, string $projectName): void
    {
        if (!isset($composerJsonContent['extra']['patchset'])) {
            return;
        }

        $packageGroups = $this->packageConfig->getPackageGroupsForParser(PackageConfigInterface::COMPOSER_TYPE_PATCHSET);
        $patchSet = $composerJsonContent['extra']['patchset'];

        foreach ($packageGroups as $packageGroup) {
            $matchedPackagesNames = preg_grep($packageGroup['regex'], array_keys($patchSet));
            foreach ($matchedPackagesNames as $matchedPackageName) {
                $patches = $patchSet[$matchedPackageName];

                foreach ($patches as $patch) {
                    $versionConstraint = $patch['version-constraint'] ?? '*';
                    $patchName = explode('/', $patch['filename']);
                    $patchName = end($patchName);

                    $this->parsedData[$packageGroup['name']][$patchName][$projectName] = ['value' => $versionConstraint, 'comment' => ''];
                }
            }
        }
    }
}
