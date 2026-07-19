<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Provider\Gitlab;

use Curl\Curl;
use EvilStudio\ComposerParser\Model\Repository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

class ApiArchiveTest extends TestCase
{
    protected Filesystem $filesystem;
    protected string $appDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->appDir = sys_get_temp_dir() . '/composer-parser-api-archive-' . bin2hex(random_bytes(6));
        $this->filesystem->mkdir($this->appDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->appDir);
    }

    public function testWriteArchiveCreatesParentDirectory(): void
    {
        $archivePath = $this->appDir . '/var/repositories/project-a.zip';

        $this->provider()->writeArchiveForTest($archivePath, 'archive-content');

        self::assertFileExists($archivePath);
        self::assertSame('archive-content', file_get_contents($archivePath));
    }

    public function testWriteArchiveThrowsWhenTargetCannotBeWritten(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Unable to write GitLab archive to "%s".', $this->appDir));

        $this->provider()->writeArchiveForTest($this->appDir, 'archive-content');
    }

    public function testLoadThrowsWhenArchiveDownloadFails(): void
    {
        $curl = $this->createStub(Curl::class);
        $curl->error = true;
        $curl->errorMessage = 'connection timed out';

        $provider = $this->provider();
        $provider->setCurl($curl);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to download GitLab archive for project "project-a": connection timed out');

        $provider->load($this->repository());
    }

    public function testLoadThrowsWhenDownloadedArchiveIsInvalid(): void
    {
        $curl = $this->createStub(Curl::class);
        $curl->error = false;
        $curl->response = 'not-a-zip-archive';

        $provider = $this->provider();
        $provider->setCurl($curl);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to open GitLab archive');

        $provider->load($this->repository());
    }

    public function testDecryptAuthJsonAllowsMissingOptionalEncryptedFile(): void
    {
        $localRepositoryDirectory = $this->appDir . '/var/repositories/project-a';
        $this->filesystem->mkdir($localRepositoryDirectory);

        $provider = $this->provider();
        $provider->setLocalRepositoryDirectory($localRepositoryDirectory);
        $provider->decryptAuthJsonForTest();

        self::assertFileDoesNotExist($localRepositoryDirectory . '/auth.json');
    }

    public function testDecryptAuthJsonRejectsEmptyEncryptedFile(): void
    {
        $localRepositoryDirectory = $this->appDir . '/var/repositories/project-a';
        $this->filesystem->mkdir($localRepositoryDirectory);
        file_put_contents($localRepositoryDirectory . '/auth.json.encrypted', '');

        $provider = $this->provider();
        $provider->setLocalRepositoryDirectory($localRepositoryDirectory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to read encrypted Composer authentication file');

        $provider->decryptAuthJsonForTest();
    }

    public function testDecryptAuthJsonRestrictsPlaintextFilePermissions(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX file permissions are not available on Windows.');
        }

        $localRepositoryDirectory = $this->appDir . '/var/repositories/project-a';
        $this->filesystem->mkdir($localRepositoryDirectory);
        $authJsonContent = '{"http-basic":{"gitlab.example.com":{"username":"token","password":"secret"}}}';
        $encryptedAuthJson = file_get_contents(dirname(__DIR__, 3) . '/Fixtures/auth.json.encrypted');
        self::assertNotFalse($encryptedAuthJson);
        file_put_contents(
            $localRepositoryDirectory . '/auth.json.encrypted',
            $encryptedAuthJson
        );

        $provider = $this->provider('vault-password');
        $provider->setLocalRepositoryDirectory($localRepositoryDirectory);
        $provider->decryptAuthJsonForTest();

        $authJsonPath = $localRepositoryDirectory . '/auth.json';
        clearstatcache(true, $authJsonPath);
        self::assertSame($authJsonContent, file_get_contents($authJsonPath));
        self::assertSame(0600, fileperms($authJsonPath) & 0777);
    }

    protected function provider(?string $ansibleVaultPassword = null): ApiArchiveTestDouble
    {
        return new ApiArchiveTestDouble(
            $this->appDir,
            'https://gitlab.example.com',
            'api-token',
            $ansibleVaultPassword
        );
    }

    protected function repository(): Repository
    {
        return new Repository([
            'name' => 'project-a',
            'directory' => 'var/repositories/project-a',
            'remote' => 'git@gitlab.example.com:team/project-a',
            'branch' => 'feature/archive',
        ]);
    }
}
