<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Provider;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository as GitRepositoryHandle;
use EvilStudio\ComposerParser\Model\Repository;
use EvilStudio\ComposerParser\Service\Provider\GitRepository as GitRepositoryProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class GitRepositoryTest extends TestCase
{
    protected Filesystem $filesystem;
    protected string $appDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->appDir = sys_get_temp_dir() . '/composer-parser-git-provider-' . bin2hex(random_bytes(6));
        $this->filesystem->mkdir($this->appDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->appDir);
    }

    public function testLoadOpensExistingRepository(): void
    {
        $localRepositoryDirectory = $this->localRepositoryDirectory();
        $this->filesystem->mkdir($localRepositoryDirectory . '/.git');

        $repositoryHandle = $this->createMock(GitRepositoryHandle::class);
        $repositoryHandle->expects($this->once())->method('checkout')->with('main');

        $git = $this->createMock(Git::class);
        $git->expects($this->once())
            ->method('open')
            ->with($localRepositoryDirectory)
            ->willReturn($repositoryHandle);
        $git->expects($this->never())->method('cloneRepository');

        $this->provider($git)->load($this->repository());
    }

    public function testLoadClonesNewRepository(): void
    {
        $localRepositoryDirectory = $this->localRepositoryDirectory();
        $repository = $this->repository();

        $repositoryHandle = $this->createMock(GitRepositoryHandle::class);
        $repositoryHandle->expects($this->once())->method('checkout')->with('main');

        $git = $this->createMock(Git::class);
        $git->expects($this->never())->method('open');
        $git->expects($this->once())
            ->method('cloneRepository')
            ->with($repository->getRemote(), $localRepositoryDirectory)
            ->willReturn($repositoryHandle);

        $this->provider($git)->load($repository);
    }

    public function testLoadPreservesCloneFailure(): void
    {
        $repository = $this->repository();
        $failure = new GitException('Git clone failed because authentication was rejected.');

        $git = $this->createMock(Git::class);
        $git->expects($this->never())->method('open');
        $git->expects($this->once())
            ->method('cloneRepository')
            ->with($repository->getRemote(), $this->localRepositoryDirectory())
            ->willThrowException($failure);

        try {
            $this->provider($git)->load($repository);
            self::fail('Expected the original clone failure to be thrown.');
        } catch (GitException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    protected function provider(Git $git): GitRepositoryProvider
    {
        return new GitRepositoryProvider($this->appDir, $git);
    }

    protected function repository(): Repository
    {
        return new Repository([
            'name' => 'project-a',
            'directory' => 'var/repositories/project-a',
            'remote' => 'git@gitlab.example.com:team/project-a.git',
            'branch' => 'main',
        ]);
    }

    protected function localRepositoryDirectory(): string
    {
        return $this->appDir . '/var/repositories/project-a';
    }
}
