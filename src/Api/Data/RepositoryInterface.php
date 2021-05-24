<?php

namespace EvilStudio\ComposerParser\Api\Data;

interface RepositoryInterface
{
    /**
     * @return string
     */
    public function getProjectName(): string;

    /**
     * @return string
     */
    public function getRepositoryName(): string;

    /**
     * @return string
     */
    public function getRemote(): string;

    /**
     * @return string
     */
    public function getBranch(): string;

    /**
     * @return string
     */
    public function getDirectory(): string;
}