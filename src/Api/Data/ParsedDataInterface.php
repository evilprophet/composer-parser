<?php

namespace EvilStudio\ComposerParser\Api\Data;

interface ParsedDataInterface
{
    public function getProjectsData(): array;

    public function getProjectNames(): array;
}