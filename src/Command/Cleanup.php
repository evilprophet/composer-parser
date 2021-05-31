<?php

namespace EvilStudio\ComposerParser\Command;

use EvilStudio\ComposerParser\Service\Cleaner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Cleanup extends Command
{
    /**
     * @var Cleaner
     */
    protected $cleaner;

    /**
     * Cleanup constructor.
     * @param Cleaner $cleaner
     * @param string|null $name
     */
    public function __construct(Cleaner $cleaner, string $name = null)
    {
        parent::__construct($name);

        $this->cleaner = $cleaner;
    }

    protected function configure()
    {
        $this
            ->setName('app:cleanup')
            ->setDescription("Run this command to remove all downloaded repositories. It's required if you want to download newest version of repositories.");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cleaner->execute();

        return 0;
    }
}