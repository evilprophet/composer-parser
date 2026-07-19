<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Provider;

use EvilStudio\ComposerParser\Api\ProviderInterface;
use EvilStudio\ComposerParser\Exception\ProviderTypeNotSupportedException;
use Psr\Container\ContainerInterface;

class ProviderManager
{
    protected string $providerType;

    protected ContainerInterface $providers;

    public function __construct(string $providerType, ContainerInterface $providers)
    {
        $this->providerType = $providerType;
        $this->providers = $providers;
    }

    public function getProvider(): ProviderInterface
    {
        if (!$this->providers->has($this->providerType)) {
            throw new ProviderTypeNotSupportedException();
        }

        $provider = $this->providers->get($this->providerType);
        if (!$provider instanceof ProviderInterface) {
            throw new ProviderTypeNotSupportedException();
        }

        return $provider;
    }
}
