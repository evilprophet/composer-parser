<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Log;

use EvilStudio\ComposerParser\Service\Log\ErrorLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ErrorLoggerTest extends TestCase
{
    public function testLogThrowableDelegatesFormattingToLogger(): void
    {
        $throwable = new RuntimeException('Something failed.');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'RuntimeException: Something failed.',
                self::callback(static fn (array $context): bool => ($context['exception'] ?? null) === $throwable)
            );

        (new ErrorLogger($logger))->logThrowable($throwable);
    }
}
