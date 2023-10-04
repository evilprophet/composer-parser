<?php

namespace EvilStudio\ComposerParser\Api\Data;

interface RepositoryInterface
{
    public function getProjectName(): string;

    public function getRepositoryName(): string;

    public function getRemoteProjectName(): string;

    public function getRemote(): string;

    public function getBranch(): string;

    public function getDirectory(): string;
}