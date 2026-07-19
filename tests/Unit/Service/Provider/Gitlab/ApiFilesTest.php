<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Provider\Gitlab;

use Curl\Curl;
use EvilStudio\ComposerParser\Model\Repository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

class ApiFilesTest extends TestCase
{
    protected Filesystem $filesystem;
    protected string $appDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->appDir = sys_get_temp_dir() . '/composer-parser-api-files-' . bin2hex(random_bytes(6));
        $this->filesystem->mkdir($this->appDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->appDir);
    }

    public function testLoadDownloadsComposerFilesFromExpectedUrls(): void
    {
        $requestedUrls = [];
        $responses = ['{"name":"vendor/project"}', '{"packages":[]}'];
        $requestIndex = 0;
        $curl = $this->createMock(Curl::class);
        $curl->error = false;
        $curl->expects(self::exactly(2))
            ->method('setHeader')
            ->with('Private-Token', 'api-token');
        $curl->expects(self::exactly(2))
            ->method('get')
            ->willReturnCallback(function (string $url) use ($curl, &$requestedUrls, $responses, &$requestIndex): void {
                $requestedUrls[] = $url;
                $curl->response = $responses[$requestIndex];
                $requestIndex++;
            });

        $provider = $this->provider();
        $provider->setCurl($curl);
        $provider->load($this->repository());

        self::assertSame([
            'https://gitlab.example.com/api/v4/projects/team%2Fproject-a/repository/files/composer%2Ejson/raw?ref=feature%2Ffiles',
            'https://gitlab.example.com/api/v4/projects/team%2Fproject-a/repository/files/composer%2Elock/raw?ref=feature%2Ffiles',
        ], $requestedUrls);
        self::assertSame(
            '{"name":"vendor/project"}',
            file_get_contents($this->appDir . '/var/repositories/project-a/composer.json')
        );
        self::assertSame(
            '{"packages":[]}',
            file_get_contents($this->appDir . '/var/repositories/project-a/composer.lock')
        );

        if (PHP_OS_FAMILY !== 'Windows') {
            $repositoryDirectory = $this->appDir . '/var/repositories/project-a';
            clearstatcache(true, $repositoryDirectory);
            self::assertSame(0, fileperms($repositoryDirectory) & 0022);
        }
    }

    public function testLoadThrowsWhenRequiredComposerJsonDownloadFails(): void
    {
        $curl = $this->createStub(Curl::class);
        $curl->error = true;
        $curl->errorMessage = 'access denied';

        $provider = $this->provider();
        $provider->setCurl($curl);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to download GitLab file "composer.json" for project "project-a": access denied');

        $provider->load($this->repository());
    }

    public function testLoadAllowsMissingOptionalComposerLock(): void
    {
        $localRepositoryDirectory = $this->appDir . '/var/repositories/project-a';
        $this->filesystem->mkdir($localRepositoryDirectory);
        file_put_contents($localRepositoryDirectory . '/composer.lock', '{"packages":[{"name":"stale/package"}]}');

        $requestIndex = 0;
        $curl = $this->createMock(Curl::class);
        $curl->expects(self::exactly(2))
            ->method('get')
            ->willReturnCallback(function () use ($curl, &$requestIndex): void {
                if ($requestIndex === 0) {
                    $curl->error = false;
                    $curl->response = '{"name":"vendor/project"}';
                } else {
                    $curl->error = true;
                    $curl->httpStatusCode = 404;
                    $curl->errorMessage = 'not found';
                }

                $requestIndex++;
            });

        $provider = $this->provider();
        $provider->setCurl($curl);
        $provider->load($this->repository());

        self::assertFileExists($this->appDir . '/var/repositories/project-a/composer.json');
        self::assertFileDoesNotExist($this->appDir . '/var/repositories/project-a/composer.lock');
    }

    protected function provider(): ApiFilesTestDouble
    {
        return new ApiFilesTestDouble($this->appDir, 'https://gitlab.example.com', 'api-token');
    }

    protected function repository(): Repository
    {
        return new Repository([
            'name' => 'project-a',
            'directory' => 'var/repositories/project-a',
            'remote' => 'git@gitlab.example.com:team/project-a',
            'branch' => 'feature/files',
        ]);
    }
}
