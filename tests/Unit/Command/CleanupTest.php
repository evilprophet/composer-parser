<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Command;

use EvilStudio\ComposerParser\Command\Cleanup;
use EvilStudio\ComposerParser\Service\App\CleanupRepositories;
use EvilStudio\ComposerParser\Service\Config\RuntimeConfigValidator;
use EvilStudio\ComposerParser\Service\Log\ErrorLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CleanupTest extends TestCase
{
    public function testExecuteReturnsSuccessAfterCleaningRepositories(): void
    {
        $runtimeConfigValidator = $this->createMock(RuntimeConfigValidator::class);
        $runtimeConfigValidator->expects(self::once())->method('validateForCleanup');
        $cleanupRepositories = $this->createMock(CleanupRepositories::class);
        $cleanupRepositories->expects(self::once())->method('execute');
        $errorLogger = $this->createMock(ErrorLogger::class);
        $errorLogger->expects(self::never())->method('logThrowable');

        $commandTester = new CommandTester(new Cleanup(
            $runtimeConfigValidator,
            static fn () => $cleanupRepositories,
            $errorLogger
        ));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString('Cleanup completed successfully', $commandTester->getDisplay());
    }

    public function testExecuteReturnsFailureAndLogsException(): void
    {
        $runtimeConfigValidator = $this->createMock(RuntimeConfigValidator::class);
        $runtimeConfigValidator->expects(self::once())->method('validateForCleanup');
        $failure = new RuntimeException('Cleanup service failed.');
        $cleanupRepositories = $this->createMock(CleanupRepositories::class);
        $cleanupRepositories->expects(self::once())->method('execute')->willThrowException($failure);
        $errorLogger = $this->createMock(ErrorLogger::class);
        $errorLogger->expects(self::once())->method('logThrowable')->with(self::identicalTo($failure));

        $commandTester = new CommandTester(new Cleanup(
            $runtimeConfigValidator,
            static fn () => $cleanupRepositories,
            $errorLogger
        ));

        self::assertSame(Command::FAILURE, $commandTester->execute([]));
        self::assertStringContainsString('Cleanup service failed.', $commandTester->getDisplay());
        self::assertStringContainsString('Cleanup failed', $commandTester->getDisplay());
    }
}
