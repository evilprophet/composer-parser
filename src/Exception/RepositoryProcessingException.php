<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Exception;

use RuntimeException;
use Throwable;

class RepositoryProcessingException extends RuntimeException
{
    public function __construct(protected string $projectName, Throwable $previous)
    {
        parent::__construct(
            sprintf('Failed to process repository "%s": %s', $projectName, $previous->getMessage()),
            0,
            $previous
        );
    }

    public function getProjectName(): string
    {
        return $this->projectName;
    }
}
