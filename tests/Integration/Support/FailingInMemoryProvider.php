<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Support;

use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use RuntimeException;

class FailingInMemoryProvider extends InMemoryProvider
{
    public function __construct(array $repositoryData, protected string $failingProjectName)
    {
        parent::__construct($repositoryData);
    }

    public function load(RepositoryInterface $repository): void
    {
        if ($repository->getProjectName() === $this->failingProjectName) {
            throw new RuntimeException('Repository data is unavailable.');
        }

        parent::load($repository);
    }
}
