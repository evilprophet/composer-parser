<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Writer\Support;

use Symfony\Component\Filesystem\Filesystem;

trait HandlesLocalOutputPath
{
    protected function getFileDirectory(): string
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->fileDirectory, 0777);

        return $this->fileDirectory;
    }

    protected function getFilePath(): string
    {
        $fileName = str_replace('{date}', date('Y-m-d'), $this->fileName);

        return $this->getFileDirectory() . DIRECTORY_SEPARATOR . $fileName . static::FILE_EXTENSION;
    }
}
