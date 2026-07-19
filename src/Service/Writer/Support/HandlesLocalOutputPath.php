<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Writer\Support;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

trait HandlesLocalOutputPath
{
    protected const int DIRECTORY_MODE = 0755;

    protected function getFileDirectory(): string
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->fileDirectory, self::DIRECTORY_MODE);

        return $this->fileDirectory;
    }

    protected function getFilePath(): string
    {
        $fileName = str_replace('{date}', date('Y-m-d'), $this->fileName);

        return $this->getFileDirectory() . DIRECTORY_SEPARATOR . $fileName . static::FILE_EXTENSION;
    }

    protected function writeLocalFile(string $content): void
    {
        $filePath = $this->getFilePath();
        if (@file_put_contents($filePath, $content) === false) {
            throw new RuntimeException(sprintf('Unable to write report to "%s".', $filePath));
        }
    }
}
