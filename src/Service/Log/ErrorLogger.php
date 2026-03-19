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
        $message = sprintf(
            "[%s] %s: %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            $throwable::class,
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine()
        );

        $this->append($message);
    }

    protected function append(string $message): void
    {
        $this->logger->error($message);
    }
}
