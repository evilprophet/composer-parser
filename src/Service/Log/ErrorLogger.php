<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Log;

use Psr\Log\LoggerInterface;
use Throwable;

class ErrorLogger
{
    public function __construct(protected LoggerInterface $logger)
    {
    }

    public function logThrowable(Throwable $throwable): void
    {
        $this->logger->error(sprintf(
            '%s: %s',
            $throwable::class,
            $throwable->getMessage()
        ), ['exception' => $throwable]);
    }
}
