<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Writer;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\WriterInterface;
use EvilStudio\ComposerParser\Service\Report\ReportFactory;
use Symfony\Component\Filesystem\Filesystem;

class Json implements WriterInterface
{
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

    protected function getFileDirectory(): string
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->fileDirectory, 0777);

        return $this->fileDirectory;
    }

    protected function getFilePath(): string
    {
        $fileName = str_replace('{date}', date('Y-m-d'), $this->fileName);

        return $this->getFileDirectory() . DIRECTORY_SEPARATOR . $fileName . self::FILE_EXTENSION;
    }

    protected function getOrderedGroups(array $groups): array
    {
        $orderedGroups = [];
        foreach ($this->packageConfig->getPackageGroupsForWriter() as $packageGroup) {
            $groupName = $packageGroup['name'];
            if (!array_key_exists($groupName, $groups)) {
                continue;
            }

            $orderedGroups[$groupName] = $groups[$groupName];
        }

        return $orderedGroups;
    }
}
