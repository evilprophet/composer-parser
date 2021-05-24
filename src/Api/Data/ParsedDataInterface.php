<?php

namespace EvilStudio\ComposerParser\Api\Data;

interface ParsedDataInterface
{
    /**
     * @return array
     */
    public function getProjectsData():array;

    /**
     * @return array
     */
    public function getProjectCodes():array;
}