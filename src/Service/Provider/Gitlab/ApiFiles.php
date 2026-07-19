<?php

namespace EvilStudio\ComposerParser\Service\Provider\Gitlab;

use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

class ApiFiles extends AbstractGitlab
{
    protected const GITLAB_API_DOWNLOAD_FILE_URL = '%s/api/v4/projects/%s/repository/files/%s/raw?ref=%s';

    protected const FILE_LIST = ['composer.json', 'composer.lock'];
    protected const OPTIONAL_FILE = 'composer.lock';
    protected const HTTP_NOT_FOUND = 404;
    protected const int DIRECTORY_MODE = 0755;
    protected const FILE_DOWNLOAD_ERROR = 'Unable to download GitLab file "%s" for project "%s": %s';
    protected const FILE_WRITE_ERROR = 'Unable to write GitLab file to "%s".';

    public function load(RepositoryInterface $repository): void
    {
        $this->localRepositoryDirectory = $this->resolveLocalRepositoryDirectory($repository);
        (new Filesystem())->mkdir($this->localRepositoryDirectory, self::DIRECTORY_MODE);

        foreach (self::FILE_LIST as $fileName) {
            $this->downloadFile($repository, $fileName);
        }
    }

    protected function downloadFile(RepositoryInterface $repository, string $fileName): void
    {
        $filePath = $this->localRepositoryDirectory . DIRECTORY_SEPARATOR . $fileName;
        $fileUrl = sprintf(
            self::GITLAB_API_DOWNLOAD_FILE_URL,
            $this->gitlabUrl,
            urlencode($repository->getRepositoryName()),
            str_replace('.', '%2E', $fileName),
            urlencode($repository->getBranch())
        );

        $curl = $this->createCurl();
        $curl->setHeader('Private-Token', $this->gitlabApiToken);
        $curl->get($fileUrl);

        if ($curl->error) {
            if ($fileName === self::OPTIONAL_FILE && $curl->httpStatusCode === self::HTTP_NOT_FOUND) {
                (new Filesystem())->remove($filePath);
                return;
            }

            throw new RuntimeException(sprintf(
                self::FILE_DOWNLOAD_ERROR,
                $fileName,
                $repository->getProjectName(),
                $curl->errorMessage ?? 'unknown error'
            ));
        }

        if (@file_put_contents($filePath, (string) $curl->response) === false) {
            throw new RuntimeException(sprintf(self::FILE_WRITE_ERROR, $filePath));
        }
    }
}
