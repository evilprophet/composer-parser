<?php

namespace EvilStudio\ComposerParser\Service\Provider\Gitlab;

use Curl\Curl;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;

class ApiFiles extends AbstractGitlab
{
    const GITLAB_API_DOWNLOAD_FILE_URL = '%s/api/v4/projects/%s/repository/files/%s/raw?ref=%s';

    const FILE_LIST = ['composer.json', 'composer.lock'];

    /**
     * @param RepositoryInterface $repository
     */
    public function load(RepositoryInterface $repository): void
    {
        $this->localRepositoryDirectory = sprintf(self::LOCAL_REPOSITORY_DIRECTORY_PATH, $this->appDir, $repository->getDirectory());
        if (!file_exists($this->localRepositoryDirectory)) {
            mkdir($this->localRepositoryDirectory, 0777, true);
        }

        foreach (self::FILE_LIST as $fileName) {
            $this->downloadFile($repository, $fileName);
        }
    }

    /**
     * @param RepositoryInterface $repository
     * @param $fileName
     */
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