<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Command;

use EvilStudio\ComposerParser\Command\Run;
use EvilStudio\ComposerParser\Service\App\RunReport;
use EvilStudio\ComposerParser\Service\Config\RuntimeConfigValidator;
use EvilStudio\ComposerParser\Service\Log\ErrorLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RunTest extends TestCase
{
    public function testExecuteReturnsSuccessAfterGeneratingReport(): void
    {
        $runtimeConfigValidator = $this->createMock(RuntimeConfigValidator::class);
        $runtimeConfigValidator->expects(self::once())->method('validateForRun');
        $runReport = $this->createMock(RunReport::class);
        $runReport->expects(self::once())->method('execute');
        $errorLogger = $this->createMock(ErrorLogger::class);
        $errorLogger->expects(self::never())->method('logThrowable');

        $commandTester = new CommandTester(new Run(
            $runtimeConfigValidator,
            static fn () => $runReport,
            $errorLogger
        ));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString('Report generation completed successfully', $commandTester->getDisplay());
    }

    public function testExecuteReturnsFailureAndLogsException(): void
    {
        $runtimeConfigValidator = $this->createMock(RuntimeConfigValidator::class);
        $runtimeConfigValidator->expects(self::once())->method('validateForRun');
        $failure = new RuntimeException('Report service failed.');
        $runReport = $this->createMock(RunReport::class);
        $runReport->expects(self::once())->method('execute')->willThrowException($failure);
        $errorLogger = $this->createMock(ErrorLogger::class);
        $errorLogger->expects(self::once())->method('logThrowable')->with(self::identicalTo($failure));

        $commandTester = new CommandTester(new Run(
            $runtimeConfigValidator,
            static fn () => $runReport,
            $errorLogger
        ));

        self::assertSame(Command::FAILURE, $commandTester->execute([]));
        self::assertStringContainsString('Report service failed.', $commandTester->getDisplay());
        self::assertStringContainsString('Report generation failed', $commandTester->getDisplay());
    }
}
