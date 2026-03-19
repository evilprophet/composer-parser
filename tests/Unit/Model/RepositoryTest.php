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
            'name' => 'americandental',
            'remote' => 'git@gitlab.creativestyle.pl:shopware/projects/americandental/americandental.git',
            'branch' => 'master',
            'directory' => 'var/repositories/americandental',
        ]);

        self::assertSame('shopware/projects/americandental/americandental', $repository->getRepositoryName());
        self::assertSame('americandental', $repository->getRemoteProjectName());
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
