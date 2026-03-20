<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Command;

use EvilStudio\ComposerParser\Service\App\RunReport;
use EvilStudio\ComposerParser\Service\Log\ErrorLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'app:run',
    description: "Run parser for all repositories configured in parameters YAML."
)]
class Run extends Command
{
    public function __construct(
        protected RunReport $runReport,
        protected ErrorLogger $errorLogger,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startedAt = microtime(true);

        try {
            $this->runReport->execute();
        } catch (Throwable $throwable) {
            $this->errorLogger->logThrowable($throwable);
            $output->writeln('<error>' . $throwable->getMessage() . '</error>');
            $output->writeln(sprintf(
                '<error>Report generation failed (%s).</error>',
                $this->formatDuration(microtime(true) - $startedAt)
            ));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>Report generation completed successfully (%s).</info>',
            $this->formatDuration(microtime(true) - $startedAt)
        ));

        return Command::SUCCESS;
    }

    protected function formatDuration(float $seconds): string
    {
        return sprintf('%.2fs', $seconds);
    }
}
