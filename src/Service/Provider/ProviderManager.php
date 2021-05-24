<?php

namespace EvilStudio\ComposerParser\Service\Provider;

use EvilStudio\ComposerParser\Api\ProviderInterface;
use EvilStudio\ComposerParser\Exception\ProviderTypeNotSupportedException;

class ProviderManager
{
    /**
     * @var string
     */
    protected $providerType;

    /**
     * @var array
     */
    protected $providers;

    /**
     * ProviderManager constructor.
     * @param string $providerType
     * @param array $providers
     */
    public function __construct(string $providerType, array $providers)
    {
        $this->providerType = $providerType;
        $this->providers = $providers;
    }

    /**
     * @return ProviderInterface
     * @throws ProviderTypeNotSupportedException
     */
    public function getProvider(): ProviderInterface
    {
        if (!key_exists($this->providerType, $this->providers)) {
            throw new ProviderTypeNotSupportedException();
        }

        return $this->providers[$this->providerType];
    }
}