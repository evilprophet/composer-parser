<?php

namespace EvilStudio\ComposerParser\Command;

use EvilStudio\ComposerParser\Api\WriterInterface;
use EvilStudio\ComposerParser\Service\Parser;
use EvilStudio\ComposerParser\Service\Writer\WriterManager;
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
     * @var WriterManager
     */
    protected $writerManager;

    public function __construct(Parser $parser, WriterManager $writerManager, string $name = null)
    {
        parent::__construct($name);

        $this->parser = $parser;
        $this->writerManager = $writerManager;
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
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parsedData = $this->parser->execute();

        /** @var WriterInterface $writer */
        $writer = $this->writerManager->getWriter();
        $writer->write($parsedData);

        return 0;
    }
}