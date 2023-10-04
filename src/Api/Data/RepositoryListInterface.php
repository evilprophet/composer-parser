<?php

namespace EvilStudio\ComposerParser\Api\Data;

interface RepositoryListInterface
{
    public function getList(): array;

    public function getProjectNames(): array;
}