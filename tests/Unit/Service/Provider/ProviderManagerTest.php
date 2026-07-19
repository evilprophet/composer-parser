<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Provider;

use EvilStudio\ComposerParser\Api\ProviderInterface;
use EvilStudio\ComposerParser\Exception\ProviderTypeNotSupportedException;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ProviderManagerTest extends TestCase
{
    public function testGetProviderReturnsConfiguredProvider(): void
    {
        $provider = $this->createStub(ProviderInterface::class);
        $unusedFactoryCalled = false;
        $manager = new ProviderManager('gitlabApiFiles', new ServiceLocator([
            'gitlabApiFiles' => static fn () => $provider,
            'gitlabApiArchive' => static function () use (&$unusedFactoryCalled, $provider): ProviderInterface {
                $unusedFactoryCalled = true;

                return $provider;
            },
        ]));

        self::assertSame($provider, $manager->getProvider());
        self::assertFalse($unusedFactoryCalled);
    }

    public function testGetProviderThrowsWhenProviderTypeIsUnknown(): void
    {
        $manager = new ProviderManager('unknown', new ServiceLocator([]));

        $this->expectException(ProviderTypeNotSupportedException::class);

        $manager->getProvider();
    }
}
