<?php

namespace EvilStudio\ComposerParser\Model;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;

class ParsedData implements ParsedDataInterface
{
    protected array $projectData;

    protected array $projectNames;

    public function __construct(array $projectData, array $projectNames)
    {
        $this->projectData = $projectData;
        $this->projectNames = $projectNames;
    }

    public function getProjectsData(): array
    {
        return $this->projectData;
    }

    public function getProjectNames(): array
    {
        return $this->projectNames;
    }
}