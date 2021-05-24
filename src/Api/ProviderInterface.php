<?php

namespace EvilStudio\ComposerParser\Api;

use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;

interface ProviderInterface
{
    const COMPOSER_JSON_PATH = '%s/composer.json';
    const COMPOSER_LOCK_PATH = '%s/composer.lock';

    /**
     * @param RepositoryInterface $repository
     */
    public function load(RepositoryInterface $repository): void;

    /**
     * @return array
     */
    public function getComposerJsonContent(): array;

    /**
     * @return array
     */
    public function getComposerLockContent(): array;

}