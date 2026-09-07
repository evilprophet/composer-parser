<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\App;

use EvilStudio\ComposerParser\Service\Parser\ParserManager;
use EvilStudio\ComposerParser\Service\Security\SecurityScanner;
use EvilStudio\ComposerParser\Service\Writer\WriterManager;

class RunReport
{
    public function __construct(
        protected ParserManager $parserManager,
        protected WriterManager $writerManager,
        protected SecurityScanner $securityScanner
    ) {
    }

    public function execute(): void
    {
        $parser = $this->parserManager->getParser();
        $parsedData = $parser->execute();

        $parsedData = $this->securityScanner->scan($parsedData);

        $writer = $this->writerManager->getWriter();
        $writer->execute($parsedData);
    }
}
