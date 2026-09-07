<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Security\PackageKey;

use EvilStudio\ComposerParser\Api\PackageKeyResolverInterface;

class ComposerPackageName implements PackageKeyResolverInterface
{
    public function getCanonicalKeysByReliability(array $lockPackage): array
    {
        $packageName = $this->getPackageName($lockPackage);
        if ($packageName === '') {
            return [];
        }

        return [[
            self::KEY_MATCHED_BY => self::MATCHED_BY_PACKAGE_NAME,
            self::KEY_CANONICAL => $packageName,
            self::KEY_SOURCE => $packageName,
        ]];
    }

    public function isExpectedToResolve(array $lockPackage): bool
    {
        return $this->getPackageName($lockPackage) !== '';
    }

    protected function getPackageName(array $lockPackage): string
    {
        return strtolower(trim((string) ($lockPackage['name'] ?? '')));
    }
}
