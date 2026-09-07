<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Security\PackageKey;

use EvilStudio\ComposerParser\Api\PackageKeyResolverInterface;
use EvilStudio\ComposerParser\Service\Security\ModuleCodeKey;

class MagentoModuleCode implements PackageKeyResolverInterface
{
    protected const string MAGENTO_MODULE_TYPE = 'magento2-module';
    protected const string NAMESPACE_SEGMENT_SEPARATOR_REGEX = '/[\\\\_]/';
    protected const string NAMESPACE_SEPARATOR = '\\';
    protected const string PACKAGE_NAME_SEPARATOR = '/';
    protected const string TECHNICAL_NAME_PART_REGEX = '/^(module|magento2|magento|extension|ext)[-_]|[-_](module|magento2|magento|extension|ext|m2)$/';
    protected const int MODULE_NAMESPACE_SEGMENT_COUNT = 2;
    protected const array AUTOLOAD_NAMESPACE_STANDARDS = ['psr-4', 'psr-0'];

    public function __construct(protected bool $matchByPackageName = true)
    {
    }

    public function getCanonicalKeysByReliability(array $lockPackage): array
    {
        $keys = [];

        foreach ($this->getNamespaceModuleCodes($lockPackage) as $canonicalKey => $namespace) {
            $keys[$canonicalKey] = [
                self::KEY_MATCHED_BY => self::MATCHED_BY_NAMESPACE,
                self::KEY_CANONICAL => $canonicalKey,
                self::KEY_SOURCE => $namespace,
            ];
        }

        if (!$this->matchByPackageName) {
            return array_values($keys);
        }

        $packageName = (string) ($lockPackage['name'] ?? '');
        $canonicalKey = $this->getPackageNameModuleCode($packageName);
        if ($canonicalKey !== null && !isset($keys[$canonicalKey])) {
            $keys[$canonicalKey] = [
                self::KEY_MATCHED_BY => self::MATCHED_BY_PACKAGE_NAME,
                self::KEY_CANONICAL => $canonicalKey,
                self::KEY_SOURCE => $packageName,
            ];
        }

        return array_values($keys);
    }

    public function isExpectedToResolve(array $lockPackage): bool
    {
        return ($lockPackage['type'] ?? '') === self::MAGENTO_MODULE_TYPE;
    }

    protected function getNamespaceModuleCodes(array $lockPackage): array
    {
        $autoload = $lockPackage['autoload'] ?? [];
        if (!is_array($autoload)) {
            return [];
        }

        $moduleCodes = [];
        foreach (self::AUTOLOAD_NAMESPACE_STANDARDS as $standard) {
            foreach (array_keys((array) ($autoload[$standard] ?? [])) as $namespace) {
                $segments = preg_split(self::NAMESPACE_SEGMENT_SEPARATOR_REGEX, (string) $namespace, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if (count($segments) !== self::MODULE_NAMESPACE_SEGMENT_COUNT) {
                    continue;
                }

                $canonicalKey = ModuleCodeKey::fromVendorAndModule($segments[0], $segments[1]);
                if ($canonicalKey === null) {
                    continue;
                }

                $moduleCodes[$canonicalKey] = implode(self::NAMESPACE_SEPARATOR, $segments);
            }
        }

        return $moduleCodes;
    }

    protected function getPackageNameModuleCode(string $packageName): ?string
    {
        if (!str_contains($packageName, self::PACKAGE_NAME_SEPARATOR)) {
            return null;
        }

        [$vendor, $name] = explode(self::PACKAGE_NAME_SEPARATOR, $packageName, 2);

        $previousName = null;
        while ($previousName !== $name) {
            $previousName = $name;
            $name = (string) preg_replace(self::TECHNICAL_NAME_PART_REGEX, '', $name);
        }

        return ModuleCodeKey::fromVendorAndModule($vendor, $name);
    }
}
