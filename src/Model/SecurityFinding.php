<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Model;

class SecurityFinding
{
    public const string STATUS_VULNERABLE = 'vulnerable';
    public const string STATUS_INCONCLUSIVE = 'inconclusive';

    public function __construct(
        protected string $projectName,
        protected string $packageName,
        protected string $listName,
        protected string $entryName,
        protected string $installedVersion,
        protected string $fixedIn,
        protected string $matchedBy,
        protected string $matchedSource,
        protected string $status,
        protected bool $uncertainEntry = false,
        protected string $referenceUrl = '',
        protected string $updateUrl = ''
    ) {
    }

    public function getProjectName(): string
    {
        return $this->projectName;
    }

    public function getPackageName(): string
    {
        return $this->packageName;
    }

    public function getListName(): string
    {
        return $this->listName;
    }

    public function getEntryName(): string
    {
        return $this->entryName;
    }

    public function getInstalledVersion(): string
    {
        return $this->installedVersion;
    }

    public function getFixedIn(): string
    {
        return $this->fixedIn;
    }

    public function getMatchedBy(): string
    {
        return $this->matchedBy;
    }

    public function getMatchedSource(): string
    {
        return $this->matchedSource;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isUncertainEntry(): bool
    {
        return $this->uncertainEntry;
    }

    public function getReferenceUrl(): string
    {
        return $this->referenceUrl;
    }

    public function getUpdateUrl(): string
    {
        return $this->updateUrl;
    }
}
