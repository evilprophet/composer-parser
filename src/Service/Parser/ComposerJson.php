<?php

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryListInterface;
use EvilStudio\ComposerParser\Api\ParserInterface;
use EvilStudio\ComposerParser\Api\ProviderInterface;
use EvilStudio\ComposerParser\Model\ParsedData;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;

class ComposerJson implements ParserInterface
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
            $this->executePerRepository($repository, $provider, $projectNamesGrouped);
        }

        return new ParsedData($this->parsedData, $projectNames);
    }

    /**
     * @param RepositoryInterface $repository
     * @param ProviderInterface $provider
     * @param array $projectNamesGrouped
     */
    protected function executePerRepository(RepositoryInterface $repository, ProviderInterface $provider, array $projectNamesGrouped)
    {
        $provider->load($repository);
        $composerJsonContent = $provider->getComposerJsonContent();
        $this->parseComposerJsonFile($composerJsonContent, $projectNamesGrouped, $repository->getProjectName());
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
            if (!key_exists($packageGroup['name'], $this->parsedData)) {
                $this->parsedData[$packageGroup['name']] = [];
            }

            $matchedPackagesNames = preg_grep($packageGroup['regex'], array_keys($section));
            foreach ($matchedPackagesNames as $matchedPackageName) {
                $this->parsedData[$packageGroup['name']][$matchedPackageName][$projectName] = ['value' => $section[$matchedPackageName]];
                unset($section[$matchedPackageName]);
            }
        }
    }
}