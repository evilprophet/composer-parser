<?php

namespace EvilStudio\ComposerParser\Service\Parser;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryListInterface;
use EvilStudio\ComposerParser\Api\ParserInterface;
use EvilStudio\ComposerParser\Api\ProviderInterface;
use EvilStudio\ComposerParser\Exception\ProviderTypeNotSupportedException;
use EvilStudio\ComposerParser\Model\ParsedData;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;

class ComposerJson implements ParserInterface
{
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
     * @throws ProviderTypeNotSupportedException
     */
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

    /**
     * @param RepositoryInterface $repository
     * @param ProviderInterface $provider
     * @param array $projectNamesGrouped
     */
    protected function executePerRepository(RepositoryInterface $repository, ProviderInterface $provider, array $projectNamesGrouped): void
    {
        $provider->load($repository);
        $composerJsonContent = $provider->getComposerJsonContent();
        $this->parseComposerJsonFile($composerJsonContent, $projectNamesGrouped, $repository->getProjectName());
        $this->parsePatchSet($composerJsonContent, $projectNamesGrouped, $repository->getProjectName());
    }

    /**
     * @param array $composerJsonContent
     * @param array $projectNamesGrouped
     * @param string $projectName
     */
    protected function parseComposerJsonFile(array $composerJsonContent, array $projectNamesGrouped, string $projectName): void
    {
        $requireGroup = $composerJsonContent['require'] ?? [];
        $replaceGroup = $composerJsonContent['replace'] ?? [];

        $this->parseGroup($requireGroup, $projectName, PackageConfigInterface::COMPOSER_TYPE_REQUIRE);
        $this->parseGroup($replaceGroup, $projectName, PackageConfigInterface::COMPOSER_TYPE_REPLACE);

        foreach ($this->parsedData as &$group) {
            ksort($group);
            foreach ($group as &$item) {
                $item = array_merge($projectNamesGrouped, $item);
            }
        }
    }

    /**
     * @param array $group
     * @param string $projectName
     * @param string $groupType
     */
    protected function parseGroup(array $group, string $projectName, string $groupType)
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

    /**
     * Function parsing data from extra/patchset section in composer.json where patches applied by mageops/php-composer-plugin-patchset are configured
     * @param array $composerJsonContent
     * @param array $projectNamesGrouped
     * @param string $projectName
     */
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