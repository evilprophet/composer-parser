<?php

namespace EvilStudio\ComposerParser\Service\Provider\Gitlab;

use Curl\Curl;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use Symfony\Component\Filesystem\Filesystem;

class ApiArchive extends AbstractGitlab
{
    const GITLAB_API_DOWNLOAD_ARCHIVE_URL = '%s/api/v4/projects/%s/repository/archive.zip?ref=%s';

    /**
     * @param RepositoryInterface $repository
     */
    public function load(RepositoryInterface $repository): void
    {
        $this->localRepositoryDirectory = sprintf(self::LOCAL_REPOSITORY_DIRECTORY_PATH, $this->appDir, $repository->getDirectory());

        $archivePath = $this->downloadArchive($repository);
        $this->unpackArchive($repository, $archivePath);
    }

    protected function downloadArchive(RepositoryInterface $repository): ?string
    {
        $fileUrl = sprintf(
            self::GITLAB_API_DOWNLOAD_ARCHIVE_URL,
            $this->gitlabUrl,
            urlencode($repository->getRepositoryName()),
            urlencode($repository->getBranch())
        );

        $curl = new Curl();
        $curl->setHeader('Private-Token', $this->gitlabApiToken);
        $curl->get($fileUrl);

        if ($curl->error) {
            return null;
        }

        $archivePath = sprintf('%s/%s.zip', $this->appDir, $repository->getDirectory());
        file_put_contents($archivePath, $curl->response);

        return $archivePath;
    }

    protected function unpackArchive(RepositoryInterface $repository, string $archivePath)
    {
        $extractDirectory = dirname($repository->getDirectory());

        $zip = new \ZipArchive();
        if ($zip->open($archivePath) !== true) {
            return;
        }
        $zip->extractTo($extractDirectory);
        $zip->close();

        $extracted = glob(sprintf('%s/%s*', $extractDirectory, $repository->getRemoteProjectName()))[0];

        $filesystem = new Filesystem();
        $filesystem->rename($extracted, $this->localRepositoryDirectory);
        $filesystem->remove($archivePath);
    }

}