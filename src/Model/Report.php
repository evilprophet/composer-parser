<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Model;

class Report
{
    public function __construct(
        protected array $projectNames,
        protected array $groups
    ) {}

    public function getProjectNames(): array
    {
        return $this->projectNames;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }
}
