<?php

namespace EvilStudio\ComposerParser\Api\Data;

use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;

interface RepositoryListInterface
{
    /**
     * @return RepositoryInterface[]
     */
    public function getList(): array;

    /**
     * @return array
     */
    public function getProjectNames(): array;

}