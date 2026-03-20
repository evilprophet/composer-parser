<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Command;

use EvilStudio\ComposerParser\Service\App\CleanupRepositories;
use EvilStudio\ComposerParser\Service\Log\ErrorLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'app:cleanup',
    description: "Run this command to remove all downloaded repositories. It's required if you want to download newest version of repositories."
)]
class Cleanup extends Command
{
    public function __construct(
        protected CleanupRepositories $cleanup,
        protected ErrorLogger $errorLogger,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startedAt = microtime(true);

        try {
            $this->cleanup->execute();
        } catch (Throwable $throwable) {
            $this->errorLogger->logThrowable($throwable);
            $output->writeln('<error>' . $throwable->getMessage() . '</error>');
            $output->writeln(sprintf(
                '<error>Cleanup failed (%s).</error>',
                $this->formatDuration(microtime(true) - $startedAt)
            ));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>Cleanup completed successfully (%s).</info>',
            $this->formatDuration(microtime(true) - $startedAt)
        ));

        return Command::SUCCESS;
    }

    protected function formatDuration(float $seconds): string
    {
        return sprintf('%.2fs', $seconds);
    }
}
