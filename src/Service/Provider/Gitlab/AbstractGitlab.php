<?php

namespace EvilStudio\ComposerParser\Service\Provider\Gitlab;

use EvilStudio\ComposerParser\Service\Provider\AbstractProvider;

abstract class AbstractGitlab extends AbstractProvider
{
    protected string $gitlabUrl;

    protected string $gitlabApiToken;

    public function __construct(string $appDir, string $gitlabUrl, string $gitlabApiToken)
    {
        parent::__construct($appDir);

        $this->gitlabUrl = $gitlabUrl;
        $this->gitlabApiToken = $gitlabApiToken;
    }
}