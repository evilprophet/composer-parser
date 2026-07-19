<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Repository;

use EvilStudio\ComposerParser\Service\Repository\RepositoryDirectoryPath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RepositoryDirectoryPathTest extends TestCase
{
    public function testResolveReturnsAPathInsideTheApplicationRepositoryWorkspace(): void
    {
        self::assertSame(
            '/application/var/repositories/team/project-a',
            RepositoryDirectoryPath::resolve('/application', 'var/repositories/team/project-a')
        );
    }

    public function testResolveRejectsDirectoriesOutsideTheRepositoryWorkspace(): void
    {
        foreach (
            [
            '../outside',
            '/tmp/project-a',
            'C:\\temp\\project-a',
            'var/repositories/../results',
            'var/repositories/./project-a',
            'var/repositories//project-a',
            'var/repositories/project-a/',
            'var/results/project-a',
            'var/repositories',
            ] as $directory
        ) {
            try {
                RepositoryDirectoryPath::resolve('/application', $directory);
                self::fail(sprintf('Expected "%s" to be rejected.', $directory));
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('relative child of var/repositories/', $exception->getMessage());
            }
        }
    }
}
