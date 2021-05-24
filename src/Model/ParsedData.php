<?php

namespace EvilStudio\ComposerParser\Model;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;

class ParsedData implements ParsedDataInterface
{
    /**
     * @var array
     */
    protected $projectData;

    /**
     * @vararray
     */
    protected $projectCodes;

    /**
     * @param array $projectData
     * @param array $projectCodes
     */
    public function __construct(array $projectData, array $projectCodes)
    {
        $this->projectData = $projectData;
        $this->projectCodes = $projectCodes;
    }

    public function getProjectsData(): array
    {
        return $this->projectData;
    }

    public function getProjectCodes(): array
    {
        return $this->projectCodes;
    }
}