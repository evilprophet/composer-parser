<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Provider;

use EvilStudio\ComposerParser\Api\ProviderInterface;
use EvilStudio\ComposerParser\Exception\ProviderTypeNotSupportedException;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;
use PHPUnit\Framework\TestCase;

class ProviderManagerTest extends TestCase
{
    public function testGetProviderReturnsConfiguredProvider(): void
    {
        $provider = $this->createStub(ProviderInterface::class);
        $manager = new ProviderManager('gitlabApiFiles', ['gitlabApiFiles' => $provider]);

        self::assertSame($provider, $manager->getProvider());
    }

    public function testGetProviderThrowsWhenProviderTypeIsUnknown(): void
    {
        $manager = new ProviderManager('unknown', []);

        $this->expectException(ProviderTypeNotSupportedException::class);

        $manager->getProvider();
    }
}
