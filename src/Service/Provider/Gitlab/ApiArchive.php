<?php

namespace EvilStudio\ComposerParser\Service\Provider\Gitlab;

use DanielNess\Ansible\Vault\Decrypter;
use DanielNess\Ansible\Vault\Decrypter\Exception\DecryptionException;
use DanielNess\Ansible\Vault\Exception\AnsibleVaultException;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

class ApiArchive extends AbstractGitlab
{
    protected const GITLAB_API_DOWNLOAD_ARCHIVE_URL = '%s/api/v4/projects/%s/repository/archive.zip?ref=%s';

    protected const AUTH_JSON_ENCRYPTED_PATH = '%s/auth.json.encrypted';
    protected const AUTH_JSON_PATH = '%s/auth.json';
    protected const ARCHIVE_WRITE_ERROR = 'Unable to write GitLab archive to "%s".';
    protected const ARCHIVE_DOWNLOAD_ERROR = 'Unable to download GitLab archive for project "%s": %s';
    protected const ARCHIVE_MISSING_ERROR = 'GitLab archive "%s" does not exist.';
    protected const ARCHIVE_OPEN_ERROR = 'Unable to open GitLab archive "%s".';
    protected const ARCHIVE_EXTRACT_ERROR = 'Unable to extract GitLab archive "%s".';
    protected const ARCHIVE_CONTENT_ERROR = 'GitLab archive for project "%s" does not contain the expected project directory.';
    protected const AUTH_READ_ERROR = 'Unable to read encrypted Composer authentication file "%s".';
    protected const AUTH_DECRYPT_ERROR = 'Unable to decrypt Composer authentication file "%s".';
    protected const AUTH_WRITE_ERROR = 'Unable to write decrypted Composer authentication file "%s".';
    protected const AUTH_PERMISSION_ERROR = 'Unable to restrict permissions for decrypted Composer authentication file "%s".';
    protected const int AUTH_JSON_FILE_MODE = 0600;

    protected string $ansibleVaultPassword;

    public function __construct(string $appDir, string $gitlabUrl, string $gitlabApiToken, ?string $ansibleVaultPassword = null)
    {
        parent::__construct($appDir, $gitlabUrl, $gitlabApiToken);

        $this->ansibleVaultPassword = $ansibleVaultPassword ?? '';
    }

    public function load(RepositoryInterface $repository): void
    {
        $this->localRepositoryDirectory = $this->resolveLocalRepositoryDirectory($repository);

        $archivePath = $this->downloadArchive($repository);
        $this->unpackArchive($repository, $archivePath);
        $this->decryptAuthJson();
    }

    protected function downloadArchive(RepositoryInterface $repository): string
    {
        $fileUrl = sprintf(
            self::GITLAB_API_DOWNLOAD_ARCHIVE_URL,
            $this->gitlabUrl,
            urlencode($repository->getRepositoryName()),
            urlencode($repository->getBranch())
        );

        $curl = $this->createCurl();
        $curl->setHeader('Private-Token', $this->gitlabApiToken);
        $curl->get($fileUrl);

        if ($curl->error) {
            throw new RuntimeException(sprintf(
                self::ARCHIVE_DOWNLOAD_ERROR,
                $repository->getProjectName(),
                $curl->errorMessage ?? 'unknown error'
            ));
        }

        $archivePath = $this->localRepositoryDirectory . '.zip';
        $this->writeArchive($archivePath, (string) $curl->response);

        return $archivePath;
    }

    protected function writeArchive(string $archivePath, string $archiveContent): void
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir(dirname($archivePath));

        if (@file_put_contents($archivePath, $archiveContent) === false) {
            throw new RuntimeException(sprintf(self::ARCHIVE_WRITE_ERROR, $archivePath));
        }
    }

    protected function unpackArchive(RepositoryInterface $repository, string $archivePath): void
    {
        if (!is_file($archivePath)) {
            throw new RuntimeException(sprintf(self::ARCHIVE_MISSING_ERROR, $archivePath));
        }

        $extractDirectory = dirname($this->localRepositoryDirectory);

        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException(sprintf(self::ARCHIVE_OPEN_ERROR, $archivePath));
        }

        if (!$zip->extractTo($extractDirectory)) {
            $zip->close();
            throw new RuntimeException(sprintf(self::ARCHIVE_EXTRACT_ERROR, $archivePath));
        }
        $zip->close();

        $extractedMatches = glob(sprintf('%s/%s*', $extractDirectory, $repository->getRemoteProjectName()));
        if (empty($extractedMatches)) {
            $filesystem = new Filesystem();
            $filesystem->remove($archivePath);
            throw new RuntimeException(sprintf(self::ARCHIVE_CONTENT_ERROR, $repository->getProjectName()));
        }
        $extracted = $extractedMatches[0];

        $filesystem = new Filesystem();
        $filesystem->rename($extracted, $this->localRepositoryDirectory);
        $filesystem->remove($archivePath);
    }

    protected function decryptAuthJson(): void
    {
        $authJsonEncryptedPath = sprintf(self::AUTH_JSON_ENCRYPTED_PATH, $this->localRepositoryDirectory);
        $authJsonPath = sprintf(self::AUTH_JSON_PATH, $this->localRepositoryDirectory);

        if (!is_file($authJsonEncryptedPath)) {
            return;
        }

        $authJsonEncryptedContent = file_get_contents($authJsonEncryptedPath);
        if ($authJsonEncryptedContent === false || $authJsonEncryptedContent === '') {
            throw new RuntimeException(sprintf(self::AUTH_READ_ERROR, $authJsonEncryptedPath));
        }

        try {
            $authJsonContent = Decrypter::decryptString($authJsonEncryptedContent, $this->ansibleVaultPassword);
        } catch (DecryptionException | AnsibleVaultException | Decrypter\Exception\InvalidPayloadException $exception) {
            throw new RuntimeException(sprintf(self::AUTH_DECRYPT_ERROR, $authJsonEncryptedPath), 0, $exception);
        }

        if (@file_put_contents($authJsonPath, $authJsonContent) === false) {
            throw new RuntimeException(sprintf(self::AUTH_WRITE_ERROR, $authJsonPath));
        }

        if (!@chmod($authJsonPath, self::AUTH_JSON_FILE_MODE)) {
            (new Filesystem())->remove($authJsonPath);
            throw new RuntimeException(sprintf(self::AUTH_PERMISSION_ERROR, $authJsonPath));
        }
    }
}
