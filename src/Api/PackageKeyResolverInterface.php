<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Api;

interface PackageKeyResolverInterface
{
    public const string MATCHED_BY_NAMESPACE = 'namespace';
    public const string MATCHED_BY_PACKAGE_NAME = 'packageName';

    public const string KEY_MATCHED_BY = 'matchedBy';
    public const string KEY_CANONICAL = 'canonicalKey';
    public const string KEY_SOURCE = 'source';

    public function getCanonicalKeysByReliability(array $lockPackage): array;

    public function isExpectedToResolve(array $lockPackage): bool;
}
