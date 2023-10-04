<?php

namespace EvilStudio\ComposerParser\Command;


use EvilStudio\ComposerParser\Service\Parser\ParserManager;
use EvilStudio\ComposerParser\Service\Writer\WriterManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Run extends Command
{
    protected ParserManager $parserManager;

    protected WriterManager $writerManager;

    public function __construct(ParserManager $parserManager, WriterManager $writerManager, string $name = null)
    {
        parent::__construct($name);

        $this->parserManager = $parserManager;
        $this->writerManager = $writerManager;
    }

    protected function configure()
    {
        $this
            ->setName('app:run')
            ->setDescription("Run this command to parser all repositories configured in 'config/parameters.yaml'.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parser = $this->parserManager->getParser();
        $parsedData = $parser->execute();

        $writer = $this->writerManager->getWriter();
        $writer->execute($parsedData);

        return 0;
    }
}