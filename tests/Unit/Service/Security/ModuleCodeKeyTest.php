<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Security;

use EvilStudio\ComposerParser\Service\Security\ModuleCodeKey;
use PHPUnit\Framework\TestCase;

class ModuleCodeKeyTest extends TestCase
{
    public function testCanonicalizeReducesModuleCodeToVendorAndModule(): void
    {
        self::assertSame('amasty|promo', ModuleCodeKey::canonicalize('Amasty_Promo'));
        self::assertSame('shopvendor|securityheaders', ModuleCodeKey::canonicalize('ShopVendor_SecurityHeaders'));
        self::assertSame('amasty|googlecustomerreviews', ModuleCodeKey::canonicalize('Amasty_GoogleCustomerReviews'));
    }

    public function testCanonicalizeIgnoresTheUnconfirmedEntrySuffix(): void
    {
        self::assertSame(
            ModuleCodeKey::canonicalize('Amasty_Adminbookmarks'),
            ModuleCodeKey::canonicalize('Amasty_Adminbookmarks?')
        );
    }

    public function testCanonicalizeRejectsValueWithoutVendorSeparator(): void
    {
        self::assertNull(ModuleCodeKey::canonicalize('Amasty'));
        self::assertNull(ModuleCodeKey::canonicalize(''));
    }

    public function testDifferentVendorsWithTheSameModuleDoNotShareAKey(): void
    {
        self::assertNotSame(
            ModuleCodeKey::canonicalize('Amasty_Rewards'),
            ModuleCodeKey::canonicalize('Mirasvit_Rewards')
        );
    }

    public function testFromVendorAndModuleRejectsEmptyParts(): void
    {
        self::assertNull(ModuleCodeKey::fromVendorAndModule('Amasty', ''));
        self::assertNull(ModuleCodeKey::fromVendorAndModule('', 'Promo'));
        self::assertNull(ModuleCodeKey::fromVendorAndModule('---', 'Promo'));
    }
}
