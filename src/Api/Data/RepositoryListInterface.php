<?php

namespace EvilStudio\ComposerParser\Api\Data;

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