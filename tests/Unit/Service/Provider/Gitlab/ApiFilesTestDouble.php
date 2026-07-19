<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Provider\Gitlab;

use Curl\Curl;
use EvilStudio\ComposerParser\Service\Provider\Gitlab\ApiFiles;

class ApiFilesTestDouble extends ApiFiles
{
    protected Curl $curl;

    public function setCurl(Curl $curl): void
    {
        $this->curl = $curl;
    }

    protected function createCurl(): Curl
    {
        return $this->curl;
    }
}
