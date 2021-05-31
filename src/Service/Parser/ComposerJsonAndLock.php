<?php

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryListInterface;
use EvilStudio\ComposerParser\Api\ParserInterface;
use EvilStudio\ComposerParser\Model\ParsedData;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;

class ComposerJsonAndLock implements ParserInterface
{
    const COMMENT_INSTALLED_VERSION = 'Installed version: %s';

    /**
     * @var PackageConfigInterface
     */
    protected $packageConfig;

    /**
     * @var RepositoryListInterface
     */
    protected $repositoryList;

    /**
     * @var ProviderManager
     */
    protected $providerManager;

    /**
     * @var array
     */
    protected $parsedData = [];

    /**
     * Parser constructor.
     * @param PackageConfigInterface $packageConfig
     * @param RepositoryListInterface $repositoryList
     * @param ProviderManager $providerManager
     */
    public function __construct(PackageConfigInterface $packageConfig, RepositoryListInterface $repositoryList, ProviderManager $providerManager)
    {
        $this->packageConfig = $packageConfig;
        $this->repositoryList = $repositoryList;
        $this->providerManager = $providerManager;
    }

    /**
     * @return ParsedDataInterface
     * @throws \EvilStudio\ComposerParser\Exception\ProviderTypeNotSupportedException
     */
    public function execute(): ParsedDataInterface
    {
        $this->parsedData = [];

        $provider = $this->providerManager->getProvider();
        $projectNames = $this->repositoryList->getProjectNames();
        $projectNamesGrouped = array_fill_keys($projectNames, ['value' => '']);

        /** @var RepositoryInterface $repository */
        foreach ($this->repositoryList->getList() as $repository) {
            $provider->load($repository);
            $composerJsonContent = $provider->getComposerJsonContent();
            $this->parseComposerJsonFile($composerJsonContent, $projectNamesGrouped, $repository->getProjectName());

            $composerLockContent = $provider->getComposerLockContent();
            if ($this->packageConfig->includeInstalledVersion() && !empty($composerLockContent)) {
                $this->parseComposerLockFile($composerLockContent, $repository->getProjectName());
            }
        }

        return new ParsedData($this->parsedData, $projectNames);
    }

    /**
     * @param array $composerJsonContent
     * @param array $projectNamesGrouped
     * @param string $projectName
     * @return array
     */
    protected function parseComposerJsonFile(array $composerJsonContent, array $projectNamesGrouped, string $projectName): array
    {
        $requireSection = $composerJsonContent['require'] ?? [];
        $replaceSection = $composerJsonContent['replace'] ?? [];

        $this->parseSection($requireSection, $projectName, PackageConfigInterface::COMPOSER_TYPE_REQUIRE);
        $this->parseSection($replaceSection, $projectName, PackageConfigInterface::COMPOSER_TYPE_REPLACE);

        foreach ($this->parsedData as &$group) {
            ksort($group);
            foreach ($group as &$item) {
                $item = array_merge($projectNamesGrouped, $item);
            }
        }

        return $this->parsedData;
    }

    /**
     * @param array $section
     * @param string $projectName
     * @param string $type
     */
    protected function parseSection(array $section, string $projectName, string $type)
    {
        $packageGroups = $this->packageConfig->getPackageGroupsForParser($type);

        foreach ($packageGroups as $packageGroup) {
            $matchedPackagesNames = preg_grep($packageGroup['regex'], array_keys($section));
            foreach ($matchedPackagesNames as $matchedPackageName) {
                $this->parsedData[$packageGroup['name']][$matchedPackageName][$projectName] = ['value' => $section[$matchedPackageName]];
                unset($section[$matchedPackageName]);
            }
        }
    }

    /**
     * @param array $composerLockContent
     * @param string $projectName
     * @return array
     */
    protected function parseComposerLockFile(array $composerLockContent, string $projectName): array
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

                switch ($this->packageConfig->installedVersionDisplayedIn()) {
                    case PackageConfigInterface::INSTALLED_VERSION_DISPLAYED_IN_VALUE:
                        $this->parsedData[$packageGroupName][$packageName][$projectName]['value'] = $packageInstalled['version'];
                        break;
                    case PackageConfigInterface::INSTALLED_VERSION_DISPLAYED_IN_COMMENT:
                        $comment = sprintf(self::COMMENT_INSTALLED_VERSION, $packageInstalled['version']);
                        $this->parsedData[$packageGroupName][$packageName][$projectName]['comment'] = $comment;
                        break;
                }
            }
        }

        return $this->parsedData;
    }
}