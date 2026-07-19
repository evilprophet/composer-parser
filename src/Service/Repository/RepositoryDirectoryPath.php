<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Repository;

use InvalidArgumentException;
use Symfony\Component\Filesystem\Path;

final class RepositoryDirectoryPath
{
    public const string BASE_DIRECTORY = 'var/repositories';

    public static function resolve(string $appDir, string $directory): string
    {
        $normalizedDirectory = Path::normalize($directory);

        if (
            $directory !== $normalizedDirectory
            || str_contains($directory, "\0")
            || Path::isAbsolute($normalizedDirectory)
            || self::containsUnsafePathSegments($normalizedDirectory)
        ) {
            throw self::invalidDirectory($directory);
        }

        $baseDirectory = Path::join($appDir, self::BASE_DIRECTORY);
        $resolvedDirectory = Path::join($appDir, $normalizedDirectory);

        if ($resolvedDirectory === $baseDirectory || !Path::isBasePath($baseDirectory, $resolvedDirectory)) {
            throw self::invalidDirectory($directory);
        }

        return $resolvedDirectory;
    }

    protected static function containsUnsafePathSegments(string $directory): bool
    {
        foreach (explode('/', $directory) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return true;
            }
        }

        return false;
    }

    protected static function invalidDirectory(string $directory): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            'Invalid repository directory "%s": it must be a relative child of %s/.',
            $directory,
            self::BASE_DIRECTORY
        ));
    }
}
