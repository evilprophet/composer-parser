<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Http;

use Curl\Curl;
use RuntimeException;

class UrlFetcher
{
    protected const string DOWNLOAD_ERROR = 'Unable to download "%s": %s';
    protected const string EMPTY_RESPONSE_ERROR = 'Downloaded file "%s" is empty.';
    protected const int TIMEOUT_SECONDS = 30;

    public function fetch(string $url): string
    {
        $curl = $this->createCurl();
        $curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
        $curl->setOpt(CURLOPT_TIMEOUT, self::TIMEOUT_SECONDS);
        $curl->get($url);

        if ($curl->error) {
            throw new RuntimeException(sprintf(self::DOWNLOAD_ERROR, $url, $curl->errorMessage ?? 'unknown error'));
        }

        $response = (string) $curl->response;
        if (trim($response) === '') {
            throw new RuntimeException(sprintf(self::EMPTY_RESPONSE_ERROR, $url));
        }

        return $response;
    }

    protected function createCurl(): Curl
    {
        return new Curl();
    }
}
