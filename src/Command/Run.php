<?php

namespace EvilStudio\ComposerParser\Command;

use EvilStudio\ComposerParser\Service\LocalSpreadsheet;
use EvilStudio\ComposerParser\Service\Parser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Run extends Command
{

    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var LocalSpreadsheet
     */
    protected $localSpreadsheet;

    public function __construct(Parser $parser, LocalSpreadsheet $localSpreadsheet, string $name = null)
    {
        parent::__construct($name);

        $this->parser = $parser;
        $this->localSpreadsheet = $localSpreadsheet;
    }

    protected function configure()
    {
        $this
            ->setName('app:run')
            ->setDescription("Run this command to parser all repositories configured in 'config/parameters.yaml'.");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Cz\Git\GitException
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parsedData = $this->parser->parseRepositories();

        $this->localSpreadsheet->write($parsedData);

        return 0;
    }
}