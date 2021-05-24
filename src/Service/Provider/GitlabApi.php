<?php

namespace EvilStudio\ComposerParser\Service\Provider;

use Curl\Curl;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\ProviderInterface;

class GitlabApi implements ProviderInterface
{
    const GITLAB_API_DOWNLOAD_FILE_URL = '%s/api/v4/projects/%s/repository/files/%s/raw?ref=%s';

    /**
     * @var string
     */
    protected $appDir;

    /**
     * @var array
     */
    protected $gitlabConfig;

    /**
     * @var string
     */
    protected $localRepositoryDirectory;

    /**
     * GitlabApi constructor.
     * @param string $appDir
     * @param array $gitlabConfig
     */
    public function __construct(string $appDir, array $gitlabConfig)
    {
        $this->appDir = $appDir;
        $this->gitlabConfig = $gitlabConfig;
    }

    /**
     * @param RepositoryInterface $repository
     */
    public function load(RepositoryInterface $repository): void
    {
        $this->localRepositoryDirectory = sprintf('%s/%s', $this->appDir, $repository->getDirectory());
        if (!file_exists($this->localRepositoryDirectory)) {
            mkdir($this->localRepositoryDirectory, 0777, true);
        }

        foreach (['composer.json', 'composer.lock'] as $fileName) {
            $this->downloadFile($repository, $fileName);
        }
    }

    /**
     * @return array
     */
    public function getComposerJsonContent(): array
    {
        $composerJsonFilePath = sprintf(self::COMPOSER_JSON_PATH, $this->localRepositoryDirectory);
        $composerJsonFileContent = file_get_contents($composerJsonFilePath);

        return json_decode($composerJsonFileContent, true);
    }

    /**
     * @return array
     */
    public function getComposerLockContent(): array
    {
        $composerLockFilePath = sprintf(self::COMPOSER_LOCK_PATH, $this->localRepositoryDirectory);
        $composerLockFileContent = file_get_contents($composerLockFilePath);

        return json_decode($composerLockFileContent, true);
    }

    protected function downloadFile(RepositoryInterface $repository, $fileName): void
    {
        $fileUrl = sprintf(
            self::GITLAB_API_DOWNLOAD_FILE_URL,
            $this->gitlabConfig['url'],
            urlencode($repository->getRepositoryName()),
            str_replace('.', '%2E', $fileName),
            urlencode($repository->getBranch())
        );

        $curl = new Curl();
        $curl->setHeader('Private-Token', $this->gitlabConfig['apiToken']);
        $curl->get($fileUrl);

        if (!$curl->error) {
            $filePath = $this->localRepositoryDirectory . DIRECTORY_SEPARATOR . $fileName;
            file_put_contents($filePath, $curl->response);
        }

    }
}