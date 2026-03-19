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
    description: "Run this command to parser all repositories configured in 'config/parameters.yaml'."
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
        try {
            $this->runReport->execute();
        } catch (Throwable $throwable) {
            $this->errorLogger->logThrowable($throwable);
            $output->writeln('<error>' . $throwable->getMessage() . '</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
