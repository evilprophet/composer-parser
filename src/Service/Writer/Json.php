<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Writer;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\WriterInterface;
use EvilStudio\ComposerParser\Service\Report\ReportFactory;
use EvilStudio\ComposerParser\Service\Writer\Support\HandlesLocalOutputPath;
use EvilStudio\ComposerParser\Service\Writer\Support\OrdersGroupsByConfig;

class Json implements WriterInterface
{
    use HandlesLocalOutputPath;
    use OrdersGroupsByConfig;

    protected const string FILE_EXTENSION = '.json';

    public function __construct(
        protected string $fileName,
        protected string $fileDirectory,
        protected PackageConfigInterface $packageConfig,
        protected ReportFactory $reportFactory
    ) {}

    public function execute(ParsedDataInterface $parsedData): void
    {
        $report = $this->reportFactory->build($parsedData);
        $payload = [
            'generatedAt' => date('c'),
            'projects' => $report->getProjectNames(),
            'groups' => $this->getOrderedGroups($report->getGroups()),
        ];

        $this->writeFile($payload);
    }

    protected function writeFile(array $payload): void
    {
        $encodedPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encodedPayload === false) {
            throw new \RuntimeException('Failed to encode report payload to JSON.');
        }

        file_put_contents($this->getFilePath(), $encodedPayload . PHP_EOL);
    }
}
