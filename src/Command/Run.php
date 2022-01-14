<?php

namespace EvilStudio\ComposerParser\Command;

use EvilStudio\ComposerParser\Exception\ParserTypeNotSupportedException;
use EvilStudio\ComposerParser\Exception\WriterTypeNotSupportedException;
use EvilStudio\ComposerParser\Service\Parser\ParserManager;
use EvilStudio\ComposerParser\Service\Writer\WriterManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Run extends Command
{
    /**
     * @var ParserManager
     */
    protected $parserManager;

    /**
     * @var WriterManager
     */
    protected $writerManager;

    /**
     * Run constructor.
     * @param ParserManager $parserManager
     * @param WriterManager $writerManager
     * @param string|null $name
     */
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

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ParserTypeNotSupportedException
     * @throws WriterTypeNotSupportedException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parser = $this->parserManager->getParser();
        $parsedData = $parser->execute();

        $writer = $this->writerManager->getWriter();
        $writer->execute($parsedData);

        return 0;
    }
}