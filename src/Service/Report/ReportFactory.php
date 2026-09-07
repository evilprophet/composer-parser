<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Report;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Model\Report;

class ReportFactory
{
    public function __construct(protected ReportValidator $validator)
    {
    }

    public function build(ParsedDataInterface $parsedData): Report
    {
        $this->validator->validate($parsedData);

        return new Report(
            $parsedData->getProjectNames(),
            $parsedData->getGroups(),
            $parsedData->getSecurityFindings(),
            $parsedData->getSecuritySummary()
        );
    }
}
