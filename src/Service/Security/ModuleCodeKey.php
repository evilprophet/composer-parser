<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Security;

final class ModuleCodeKey
{
    public const string VENDOR_SEPARATOR = '|';

    protected const string MODULE_CODE_SEPARATOR = '_';
    protected const string UNCONFIRMED_ENTRY_SUFFIX = '?';
    protected const string NON_CANONICAL_CHARACTERS_REGEX = '/[^a-z0-9]/';

    public static function canonicalize(string $moduleCode): ?string
    {
        $moduleCode = rtrim(trim($moduleCode), self::UNCONFIRMED_ENTRY_SUFFIX);
        if (!str_contains($moduleCode, self::MODULE_CODE_SEPARATOR)) {
            return null;
        }

        [$vendor, $module] = explode(self::MODULE_CODE_SEPARATOR, $moduleCode, 2);

        return self::fromVendorAndModule($vendor, $module);
    }

    public static function fromVendorAndModule(string $vendor, string $module): ?string
    {
        $vendor = self::reduce($vendor);
        $module = self::reduce($module);

        if ($vendor === '' || $module === '') {
            return null;
        }

        return $vendor . self::VENDOR_SEPARATOR . $module;
    }

    protected static function reduce(string $value): string
    {
        return (string) preg_replace(self::NON_CANONICAL_CHARACTERS_REGEX, '', strtolower($value));
    }
}
