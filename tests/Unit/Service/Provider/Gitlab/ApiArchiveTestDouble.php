<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Provider\Gitlab;

use Curl\Curl;
use EvilStudio\ComposerParser\Service\Provider\Gitlab\ApiArchive;

class ApiArchiveTestDouble extends ApiArchive
{
    protected Curl $curl;

    public function setCurl(Curl $curl): void
    {
        $this->curl = $curl;
    }

    public function setLocalRepositoryDirectory(string $directory): void
    {
        $this->localRepositoryDirectory = $directory;
    }

    public function writeArchiveForTest(string $archivePath, string $archiveContent): void
    {
        $this->writeArchive($archivePath, $archiveContent);
    }

    public function decryptAuthJsonForTest(): void
    {
        $this->decryptAuthJson();
    }

    protected function createCurl(): Curl
    {
        return $this->curl;
    }
}
