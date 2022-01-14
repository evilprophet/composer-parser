<?php

namespace EvilStudio\ComposerParser\Service\Provider\Gitlab;

use EvilStudio\ComposerParser\Service\Provider\AbstractProvider;

abstract class AbstractGitlab extends AbstractProvider
{
    /**
     * @var string
     */
    protected $gitlabUrl;

    /**
     * @var string
     */
    protected $gitlabApiToken;

    /**
     * GitlabApi constructor.
     * @param string $appDir
     * @param string $gitlabUrl
     * @param string $gitlabApiToken
     */
    public function __construct(string $appDir, string $gitlabUrl, string $gitlabApiToken)
    {
        parent::__construct($appDir);

        $this->gitlabUrl = $gitlabUrl;
        $this->gitlabApiToken = $gitlabApiToken;
    }
}