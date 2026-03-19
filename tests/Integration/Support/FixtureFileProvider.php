<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Support;

use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Service\Provider\AbstractProvider;

class FixtureFileProvider extends AbstractProvider
{
    public function __construct(string $fixtureBasePath)
    {
        parent::__construct($fixtureBasePath);
    }

    public function load(RepositoryInterface $repository): void
    {
        $this->localRepositoryDirectory = sprintf('%s/%s', $this->appDir, $repository->getDirectory());
    }
}
