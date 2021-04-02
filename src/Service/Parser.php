<?php

namespace EvilStudio\ComposerParser\Service;

use Cz\Git\GitException;
use Cz\Git\GitRepository;

class Parser
{
    const COMPOSER_TYPE_REQUIRE = 'require';
    const COMPOSER_TYPE_REPLACE = 'replace';

    /**
     * @var string
     */
    protected $appDir;

    /**
     * @var array
     */
    protected $repositoriesConfig;

    /**
     * @var array
     */
    protected $parserConfig;

    /**
     * @var string
     */
    protected $localDirectoryTemp;

    /**
     * @var array
     */
    protected $parsedData = [];

    /**
     * Parser constructor.
     * @param string $appDir
     * @param array $repositoriesConfig
     * @param array $parserConfig
     */
    public function __construct(string $appDir, array $repositoriesConfig, array $parserConfig)
    {
        $this->appDir = $appDir;
        $this->repositoriesConfig = $repositoriesConfig;
        $this->parserConfig = $parserConfig;
    }

    /**
     * @return array
     * @throws GitException
     */
    public function parseRepositories(): array
    {
        $this->parsedData = [];
        $projectCodesGrouped = [];
        $branchesGrouped = [];

        foreach ($this->repositoriesConfig as $repositoryName => $repositoryConfig) {
            $branches = $repositoryConfig['observed_branches'];
            $branchesGrouped[$repositoryName] = $branches;

            $projectCodes = $this->getProjectCodes($branches, $repositoryName);
            $projectCodesGrouped = array_merge($projectCodes, $projectCodesGrouped);
        }

        ksort($projectCodesGrouped);

        foreach ($this->repositoriesConfig as $repositoryName => $repositoryConfig) {
            $repository = $this->loadRepository($repositoryConfig);
            $this->parseComposerJsonFiles($repository, $branchesGrouped[$repositoryName], $projectCodesGrouped, $repositoryName);
        }

        return ['projectData' => $this->parsedData, 'projectCodes' => $projectCodesGrouped];
    }

    /**
     * @param GitRepository $repository
     * @param array $branches
     * @param array $projectCodes
     * @param string $repositoryName
     * @return array
     * @throws GitException
     */
    public function parseComposerJsonFiles(GitRepository $repository, array $branches, array $projectCodes, string $repositoryName): array
    {
        foreach ($branches as $branch) {
            $parsedComposer = $this->getParsedComposerJson($repository, $branch);
            $requireSection = $parsedComposer['require'] ?? [];
            $replaceSection = $parsedComposer['replace'] ?? [];

            $this->parseSection($requireSection, $branch, $repositoryName, self::COMPOSER_TYPE_REQUIRE);
            $this->parseSection($replaceSection, $branch, $repositoryName, self::COMPOSER_TYPE_REPLACE);
        }

        foreach ($this->parsedData as &$group) {
            ksort($group);
            foreach ($group as &$item) {
                $item = array_merge($projectCodes, $item);
            }
        }

        return $this->parsedData;
    }

    /**
     * @param array $branches
     * @param string $repositoryName
     * @return array
     */
    public function getProjectCodes(array $branches, string $repositoryName): array
    {
        $projectCodes = [];

        foreach ($branches as $branch) {
            $projectCode = $this->getProductNameWithRepository($branch, $repositoryName);
            $projectCodes[$projectCode] = '';
        }

        return $projectCodes;
    }

    /**
     * @param array $repositoryConfig
     * @return GitRepository
     * @throws GitException
     */
    protected function loadRepository(array $repositoryConfig): GitRepository
    {
        $this->localDirectoryTemp = sprintf('%s/%s', $this->appDir, $repositoryConfig['directory']);
        try {
            $repository = GitRepository::cloneRepository($repositoryConfig['remote'], $this->localDirectoryTemp);
        } catch (GitException $exception) {
            $repository = new GitRepository($this->localDirectoryTemp);
        }

        return $repository;
    }

    /**
     * @param GitRepository $repository
     * @param string $branch
     * @return mixed
     * @throws GitException
     */
    protected function getParsedComposerJson(GitRepository $repository, string $branch)
    {
        $repository->checkout($branch);
//        $repository->pull();
        $composerJsonPath = sprintf('%s/composer.json', $this->localDirectoryTemp);
        $composerJsonFile = file_get_contents($composerJsonPath);

        return json_decode($composerJsonFile, true);
    }

    /**
     * @param array $section
     * @param string $branch
     * @param string $repositoryName
     * @param string $type
     */
    protected function parseSection(array $section, string $branch, string $repositoryName, string $type = self::COMPOSER_TYPE_REQUIRE)
    {
        $projectCode = $this->getProductNameWithRepository($branch, $repositoryName);

        $packagesGroups = $this->parserConfig['packages_groups'];
        usort($packagesGroups, function ($a, $b) {
            return $a['priority'] > $b['priority'] ? -1 : 1;
        });
        $packagesGroups = array_filter($packagesGroups, function ($item) use ($type) {
            return $item['type'] == $type;
        });

        foreach ($packagesGroups as $packagesGroup) {
            $matchedExtensionNames = preg_grep($packagesGroup['regex'], array_keys($section));
            foreach ($matchedExtensionNames as $matchedExtensionName) {
                $this->parsedData[$packagesGroup['name']][$matchedExtensionName][$projectCode] = $section[$matchedExtensionName];
                unset($section[$matchedExtensionName]);
            }
        }
    }

    /**
     * @param string $branchName
     * @param string $repositoryName
     * @return string
     */
    protected function getProductNameWithRepository(string $branchName, string $repositoryName): string
    {
        $projectCode = explode('/', $branchName);
        $projectCode = end($projectCode);

        return sprintf('%s - %s', $repositoryName, $projectCode);
    }
}