<?php

namespace EvilStudio\ComposerParser\Service;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryListInterface;
use EvilStudio\ComposerParser\Model\ParsedData;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;

class Parser
{
    const COMPOSER_TYPE_REQUIRE = 'require';
    const COMPOSER_TYPE_REPLACE = 'replace';

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
        $projectNamesGrouped = array_fill_keys($projectNames, '');

        /** @var RepositoryInterface $repository */
        foreach ($this->repositoryList->getList() as $repository) {
            $provider->load($repository);
            $composerJsonContent = $provider->getComposerJsonContent();
            $this->parseComposerJsonFiles($composerJsonContent, $projectNamesGrouped, $repository->getProjectName());
        }

        return new ParsedData($this->parsedData, $projectNames);
    }

    /**
     * @param array $composerJsonContent
     * @param array $projectNamesGrouped
     * @param string $projectName
     * @return array
     */
    public function parseComposerJsonFiles(array $composerJsonContent, array $projectNamesGrouped, string $projectName): array
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
        $packagesGroups = $this->packageConfig->getPackageGroupsForParser($type);

        foreach ($packagesGroups as $packagesGroup) {
            $matchedExtensionNames = preg_grep($packagesGroup['regex'], array_keys($section));
            foreach ($matchedExtensionNames as $matchedExtensionName) {
                $this->parsedData[$packagesGroup['name']][$matchedExtensionName][$projectName] = $section[$matchedExtensionName];
                unset($section[$matchedExtensionName]);
            }
        }
    }
}