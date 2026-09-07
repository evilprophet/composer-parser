<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Security;

class VersionComparator
{
    protected const string DEV_VERSION_PREFIX = 'dev-';
    protected const string DEV_VERSION_SUFFIX = '-dev';
    protected const string VERSION_PREFIX_CHARACTERS = 'vV';
    protected const string COMPARABLE_VERSION_REGEX = '/^\d+(\.\d+)*([.\-+][0-9A-Za-z.\-+]+)?$/';
    protected const string NOT_A_VERSION_DATE_REGEX = '/^\d{4}-\d{1,2}-\d{1,2}$/';


    public function isOlderThan(string $installedVersion, string $fixedVersion): ?bool
    {
        $installed = $this->normalize($installedVersion);
        $fixed = $this->normalize($fixedVersion);

        if (!$this->isComparable($installed) || !$this->isComparable($fixed)) {
            return null;
        }

        return version_compare($installed, $fixed) < 0;
    }

    protected function normalize(string $version): string
    {
        return ltrim(trim($version), self::VERSION_PREFIX_CHARACTERS);
    }

    protected function isComparable(string $version): bool
    {
        if ($version === '' || str_starts_with($version, self::DEV_VERSION_PREFIX) || str_ends_with($version, self::DEV_VERSION_SUFFIX)) {
            return false;
        }

        if (preg_match(self::NOT_A_VERSION_DATE_REGEX, $version) === 1) {
            return false;
        }

        return preg_match(self::COMPARABLE_VERSION_REGEX, $version) === 1;
    }
}
