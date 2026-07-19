<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Model;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;

class ParsedData implements ParsedDataInterface
{
    protected array $groups;
    protected array $projectNames;

    public function __construct(array $groups, array $projectNames)
    {
        $this->groups = $groups;
        $this->projectNames = $projectNames;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getProjectNames(): array
    {
        return $this->projectNames;
    }
}
