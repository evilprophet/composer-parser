<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Security\PackageKey;

use EvilStudio\ComposerParser\Api\PackageKeyResolverInterface;
use EvilStudio\ComposerParser\Service\Security\ModuleCodeKey;
use EvilStudio\ComposerParser\Service\Security\PackageKey\MagentoModuleCode;
use PHPUnit\Framework\TestCase;

class MagentoModuleCodeTest extends TestCase
{
    public function testNamespaceResolvesModuleCodeWhenPackageNameDoesNotContainIt(): void
    {
        $keys = (new MagentoModuleCode())->getCanonicalKeysByReliability([
            'name' => 'vendor/magento2-extension',
            'type' => 'magento2-module',
            'autoload' => ['psr-4' => ['ShopVendor\\Checkout\\' => '']],
        ]);

        self::assertSame(ModuleCodeKey::canonicalize('ShopVendor_Checkout'), $keys[0][PackageKeyResolverInterface::KEY_CANONICAL]);
        self::assertSame(PackageKeyResolverInterface::MATCHED_BY_NAMESPACE, $keys[0][PackageKeyResolverInterface::KEY_MATCHED_BY]);
        self::assertSame('ShopVendor\\Checkout', $keys[0][PackageKeyResolverInterface::KEY_SOURCE]);
    }

    public function testNamespaceResolvesModuleCodeWhenVendorDiffersFromPackageVendor(): void
    {
        $keys = (new MagentoModuleCode())->getCanonicalKeysByReliability([
            'name' => 'vendor/shop-security-headers',
            'type' => 'magento2-module',
            'autoload' => ['psr-4' => ['ShopVendor\\SecurityHeaders\\' => '']],
        ]);

        self::assertSame(ModuleCodeKey::canonicalize('ShopVendor_SecurityHeaders'), $keys[0][PackageKeyResolverInterface::KEY_CANONICAL]);
    }

    public function testOnePackageResolvesEveryModuleItAutoloads(): void
    {
        $keys = (new MagentoModuleCode())->getCanonicalKeysByReliability([
            'name' => 'mirasvit/module-rewards',
            'type' => 'magento2-module',
            'autoload' => ['psr-4' => [
                'Mirasvit\\Rewards\\' => 'src/Rewards',
                'Mirasvit\\RewardsApi\\' => 'src/RewardsApi',
                'Mirasvit\\RewardsCheckout\\' => 'src/RewardsCheckout',
            ]],
        ]);

        $canonicalKeys = array_column($keys, PackageKeyResolverInterface::KEY_CANONICAL);

        self::assertContains(ModuleCodeKey::canonicalize('Mirasvit_Rewards'), $canonicalKeys);
        self::assertContains(ModuleCodeKey::canonicalize('Mirasvit_RewardsApi'), $canonicalKeys);
        self::assertContains(ModuleCodeKey::canonicalize('Mirasvit_RewardsCheckout'), $canonicalKeys);
    }

    public function testNamespaceWithoutTwoSegmentsProducesNoNamespaceKey(): void
    {
        $keys = (new MagentoModuleCode(false))->getCanonicalKeysByReliability([
            'name' => 'vendor/library',
            'type' => 'library',
            'autoload' => ['psr-4' => ['Amasty\\' => 'src/', 'Deep\\Nested\\Namespaced\\' => 'src/']],
        ]);

        self::assertSame([], $keys);
    }

    public function testPsr0WithUnderscoreConventionResolvesModuleCode(): void
    {
        $keys = (new MagentoModuleCode(false))->getCanonicalKeysByReliability([
            'name' => 'vendor/legacy',
            'type' => 'magento2-module',
            'autoload' => ['psr-0' => ['Fooman_Tcpdf' => 'lib/']],
        ]);

        self::assertSame(ModuleCodeKey::canonicalize('Fooman_Tcpdf'), $keys[0][PackageKeyResolverInterface::KEY_CANONICAL]);
    }

    public function testPackageNameFallbackStripsTechnicalNameParts(): void
    {
        $resolver = new MagentoModuleCode();

        self::assertSame(
            ModuleCodeKey::canonicalize('Amasty_Promo'),
            $resolver->getCanonicalKeysByReliability(['name' => 'amasty/promo'])[0][PackageKeyResolverInterface::KEY_CANONICAL]
        );
        self::assertSame(
            ModuleCodeKey::canonicalize('Amasty_GdprPro'),
            $resolver->getCanonicalKeysByReliability(['name' => 'amasty/module-gdpr-pro'])[0][PackageKeyResolverInterface::KEY_CANONICAL]
        );
        self::assertSame(
            ModuleCodeKey::canonicalize('Fooman_PdfCustomiser'),
            $resolver->getCanonicalKeysByReliability(['name' => 'fooman/pdfcustomiser-m2'])[0][PackageKeyResolverInterface::KEY_CANONICAL]
        );
    }

    public function testPackageNameFallbackKeepsTheVendorAnchored(): void
    {
        $resolver = new MagentoModuleCode();

        $unrelatedPairs = [
            ['symfony/finder', 'Amasty_Finder'],
            ['tecnickcom/tcpdf', 'Fooman_Tcpdf'],
            ['mirasvit/module-rewards', 'Amasty_Rewards'],
            ['magento/module-gift-card-graph-ql', 'Amasty_GiftCardGraphQl'],
            ['aheadworks/module-blog', 'Mirasvit_Blog'],
        ];

        foreach ($unrelatedPairs as [$packageName, $unrelatedModuleCode]) {
            $canonicalKeys = array_column(
                $resolver->getCanonicalKeysByReliability(['name' => $packageName]),
                PackageKeyResolverInterface::KEY_CANONICAL
            );

            self::assertNotContains(ModuleCodeKey::canonicalize($unrelatedModuleCode), $canonicalKeys, $packageName);
        }
    }

    public function testPackageNameFallbackCanBeDisabled(): void
    {
        self::assertSame([], (new MagentoModuleCode(false))->getCanonicalKeysByReliability(['name' => 'amasty/promo']));
    }

    public function testNamespaceIsOfferedBeforeThePackageName(): void
    {
        $keys = (new MagentoModuleCode())->getCanonicalKeysByReliability([
            'name' => 'vendor/shop-security-headers',
            'type' => 'magento2-module',
            'autoload' => ['psr-4' => ['ShopVendor\\SecurityHeaders\\' => '']],
        ]);

        self::assertSame(PackageKeyResolverInterface::MATCHED_BY_NAMESPACE, $keys[0][PackageKeyResolverInterface::KEY_MATCHED_BY]);
        self::assertSame(PackageKeyResolverInterface::MATCHED_BY_PACKAGE_NAME, $keys[1][PackageKeyResolverInterface::KEY_MATCHED_BY]);
    }

    public function testOnlyMagentoModulesAreExpectedToResolve(): void
    {
        $resolver = new MagentoModuleCode();

        self::assertTrue($resolver->isExpectedToResolve(['name' => 'amasty/promo', 'type' => 'magento2-module']));
        self::assertFalse($resolver->isExpectedToResolve(['name' => 'symfony/console', 'type' => 'library']));
        self::assertFalse($resolver->isExpectedToResolve(['name' => 'vendor/theme', 'type' => 'magento2-theme']));
    }
}
