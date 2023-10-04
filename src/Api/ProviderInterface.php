<?php

namespace EvilStudio\ComposerParser\Api;

use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;

interface ProviderInterface
{
    public const LOCAL_REPOSITORY_DIRECTORY_PATH = '%s/%s';

    public const COMPOSER_JSON_PATH = '%s/composer.json';
    public const COMPOSER_LOCK_PATH = '%s/composer.lock';

    public function load(RepositoryInterface $repository): void;

    public function getComposerJsonContent(): array;

    public function getComposerLockContent(): array;

}