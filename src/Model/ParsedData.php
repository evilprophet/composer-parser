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
    protected $projectNames;

    /**
     * @param array $projectData
     * @param array $projectNames
     */
    public function __construct(array $projectData, array $projectNames)
    {
        $this->projectData = $projectData;
        $this->projectNames = $projectNames;
    }

    /**
     * @return array
     */
    public function getProjectsData(): array
    {
        return $this->projectData;
    }

    /**
     * @return array
     */
    public function getProjectNames(): array
    {
        return $this->projectNames;
    }
}