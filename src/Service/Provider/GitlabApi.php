<?php

namespace EvilStudio\ComposerParser\Service\Provider;

use Curl\Curl;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;

class GitlabApi extends AbstractProvider
{
    const GITLAB_API_DOWNLOAD_FILE_URL = '%s/api/v4/projects/%s/repository/files/%s/raw?ref=%s';

    /**
     * @var string
     */
    protected $gitlabUrl;

    /**
     * @var string
     */
    protected $gitlabApiToken;

    /**
     * GitlabApi constructor.
     * @param string $appDir
     * @param string $gitlabUrl
     * @param string $gitlabApiToken
     */
    public function __construct(string $appDir, string $gitlabUrl, string $gitlabApiToken)
    {
        parent::__construct($appDir);

        $this->gitlabUrl = $gitlabUrl;
        $this->gitlabApiToken = $gitlabApiToken;
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

    protected function downloadFile(RepositoryInterface $repository, $fileName): void
    {
        $fileUrl = sprintf(
            self::GITLAB_API_DOWNLOAD_FILE_URL,
            $this->gitlabUrl,
            urlencode($repository->getRepositoryName()),
            str_replace('.', '%2E', $fileName),
            urlencode($repository->getBranch())
        );

        $curl = new Curl();
        $curl->setHeader('Private-Token', $this->gitlabApiToken);
        $curl->get($fileUrl);

        if (!$curl->error) {
            $filePath = $this->localRepositoryDirectory . DIRECTORY_SEPARATOR . $fileName;
            file_put_contents($filePath, $curl->response);
        }

    }
}