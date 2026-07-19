<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service;

use EvilStudio\ComposerParser\Model\RepositoryList;
use EvilStudio\ComposerParser\Service\Cleaner;
use InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class CleanerTest extends TestCase
{
    protected string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/composer-parser-cleaner-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->temporaryDirectory);
    }

    public function testExecuteRemovesTheApplicationCheckoutRatherThanTheCurrentWorkingDirectory(): void
    {
        $appDir = $this->temporaryDirectory . '/application';
        $otherWorkingDirectory = $this->temporaryDirectory . '/other-working-directory';
        $applicationCheckout = $appDir . '/var/repositories/project-a';
        $currentWorkingDirectoryCheckout = $otherWorkingDirectory . '/var/repositories/project-a';

        mkdir($applicationCheckout, 0777, true);
        mkdir($currentWorkingDirectoryCheckout, 0777, true);

        $cleaner = new Cleaner(new RepositoryList([
            [
                'name' => 'project-a',
                'directory' => 'var/repositories/project-a',
                'remote' => 'git@gitlab.example.com:team/project-a.git',
                'branch' => 'main',
            ],
        ]), $appDir);

        $originalWorkingDirectory = getcwd();
        self::assertIsString($originalWorkingDirectory);

        try {
            chdir($otherWorkingDirectory);
            $cleaner->execute();
        } finally {
            chdir($originalWorkingDirectory);
        }

        self::assertDirectoryDoesNotExist($applicationCheckout);
        self::assertDirectoryExists($currentWorkingDirectoryCheckout);
    }

    public function testExecuteRejectsAnUnsafeDirectoryBeforeDeletingAnything(): void
    {
        $appDir = $this->temporaryDirectory . '/application';
        $outsideDirectory = $this->temporaryDirectory . '/outside';
        mkdir($outsideDirectory, 0777, true);

        $cleaner = new Cleaner(new RepositoryList([
            [
                'name' => 'project-a',
                'directory' => '../outside',
                'remote' => 'git@gitlab.example.com:team/project-a.git',
                'branch' => 'main',
            ],
        ]), $appDir);

        $this->expectException(InvalidArgumentException::class);

        try {
            $cleaner->execute();
        } finally {
            self::assertDirectoryExists($outsideDirectory);
        }
    }
}
