<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Model;

use EvilStudio\ComposerParser\Model\Repository;
use PHPUnit\Framework\TestCase;

class RepositoryTest extends TestCase
{
    public function testParsesSshRemoteWithSubgroups(): void
    {
        $repository = new Repository([
            'name' => 'project-b',
            'remote' => 'git@gitlab.example.com:team/projects/project-b/project-b.git',
            'branch' => 'master',
            'directory' => 'var/repositories/project-b',
        ]);

        self::assertSame('team/projects/project-b/project-b', $repository->getRepositoryName());
        self::assertSame('project-b', $repository->getRemoteProjectName());
    }

    public function testParsesHttpsRemote(): void
    {
        $repository = new Repository([
            'name' => 'project-a',
            'remote' => 'https://gitlab.example.com/team/subgroup/project-a.git',
            'branch' => 'main',
            'directory' => 'var/repositories/project-a',
        ]);

        self::assertSame('team/subgroup/project-a', $repository->getRepositoryName());
        self::assertSame('project-a', $repository->getRemoteProjectName());
    }
}
