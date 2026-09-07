<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Model;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;

class ParsedData implements ParsedDataInterface
{
    protected array $groups;
    protected array $projectNames;
    protected array $securityFindings;
    protected string $securitySummary;

    public function __construct(
        array $groups,
        array $projectNames,
        array $securityFindings = [],
        string $securitySummary = ''
    ) {
        $this->groups = $groups;
        $this->projectNames = $projectNames;
        $this->securityFindings = $securityFindings;
        $this->securitySummary = $securitySummary;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getProjectNames(): array
    {
        return $this->projectNames;
    }

    public function getSecurityFindings(): array
    {
        return $this->securityFindings;
    }

    public function getSecuritySummary(): string
    {
        return $this->securitySummary;
    }
}
