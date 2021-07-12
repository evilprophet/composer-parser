<?php

namespace EvilStudio\ComposerParser\Service\Provider\Gitlab;

use Curl\Curl;
use DanielNess\Ansible\Vault\Decrypter;
use DanielNess\Ansible\Vault\Decrypter\Exception\DecryptionException;
use DanielNess\Ansible\Vault\Exception\AnsibleVaultException;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use Symfony\Component\Filesystem\Filesystem;

class ApiArchive extends AbstractGitlab
{
    const GITLAB_API_DOWNLOAD_ARCHIVE_URL = '%s/api/v4/projects/%s/repository/archive.zip?ref=%s';

    const AUTH_JSON_ENCRYPTED_PATH = '%s/auth.json.encrypted';
    const AUTH_JSON_PATH = '%s/auth.json';

    /**
     * @var string
     */
    protected $ansibleVaultPassword;

    public function __construct(string $appDir, string $gitlabUrl, string $gitlabApiToken, string $ansibleVaultPassword)
    {
        parent::__construct($appDir, $gitlabUrl, $gitlabApiToken);

        $this->ansibleVaultPassword = $ansibleVaultPassword;
    }

    /**
     * @param RepositoryInterface $repository
     */
    public function load(RepositoryInterface $repository): void
    {
        $this->localRepositoryDirectory = sprintf(self::LOCAL_REPOSITORY_DIRECTORY_PATH, $this->appDir, $repository->getDirectory());

        $archivePath = $this->downloadArchive($repository);
        $this->unpackArchive($repository, $archivePath);
        $this->decryptAuthJson();
    }

    /**
     * @param RepositoryInterface $repository
     * @return string|null
     */
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

    /**
     * @param RepositoryInterface $repository
     * @param string $archivePath
     */
    protected function unpackArchive(RepositoryInterface $repository, string $archivePath): void
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

    protected function decryptAuthJson(): void
    {
        $authJsonEncryptedPath = sprintf(self::AUTH_JSON_ENCRYPTED_PATH, $this->localRepositoryDirectory);
        $authJsonPath = sprintf(self::AUTH_JSON_PATH, $this->localRepositoryDirectory);

        $authJsonEncryptedContent = @file_get_contents($authJsonEncryptedPath);
        if (empty($authJsonEncryptedContent)) {
            return;
        }

        try {
            $authJsonContent = Decrypter::decryptString($authJsonEncryptedContent, $this->ansibleVaultPassword);
        } catch (DecryptionException | AnsibleVaultException | Decrypter\Exception\InvalidPayloadException $e) {
            return;
        }

        file_put_contents($authJsonPath, $authJsonContent);
    }

    /**
     * @return string
     */
    public function getLocalRepositoryDirectory(): string
    {
        return $this->localRepositoryDirectory;
    }
}