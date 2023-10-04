<?php

namespace EvilStudio\ComposerParser\Service\Provider;

use EvilStudio\ComposerParser\Api\ProviderInterface;
use EvilStudio\ComposerParser\Exception\ProviderTypeNotSupportedException;

class ProviderManager
{
    protected string $providerType;

    protected array $providers;

    public function __construct(string $providerType, array $providers)
    {
        $this->providerType = $providerType;
        $this->providers = $providers;
    }

    public function getProvider(): ProviderInterface
    {
        if (!key_exists($this->providerType, $this->providers)) {
            throw new ProviderTypeNotSupportedException();
        }

        return $this->providers[$this->providerType];
    }
}