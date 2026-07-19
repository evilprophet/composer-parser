<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Service\Provider;

use EvilStudio\ComposerParser\Model\Repository;
use EvilStudio\ComposerParser\Tests\Integration\Support\FixtureFileProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AbstractProviderTest extends TestCase
{
    public function testMissingComposerJsonThrowsReadableException(): void
    {
        $provider = new FixtureFileProvider(__DIR__ . '/../../Fixtures');
        $provider->load($this->missingRepository());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Required Composer file "composer.json" could not be read.');

        $provider->getComposerJsonContent();
    }

    public function testMissingComposerLockRemainsOptional(): void
    {
        $provider = new FixtureFileProvider(__DIR__ . '/../../Fixtures');
        $provider->load($this->missingRepository());

        self::assertSame([], $provider->getComposerLockContent());
    }

    public function testMalformedComposerJsonThrowsReadableException(): void
    {
        $provider = new FixtureFileProvider(__DIR__ . '/../../Fixtures');
        $provider->load($this->repository('malformed-composer-json'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Composer file "composer.json" contains invalid JSON:');

        $provider->getComposerJsonContent();
    }

    public function testMalformedComposerLockThrowsReadableException(): void
    {
        $provider = new FixtureFileProvider(__DIR__ . '/../../Fixtures');
        $provider->load($this->repository('malformed-composer-lock'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Composer file "composer.lock" contains invalid JSON:');

        $provider->getComposerLockContent();
    }

    protected function missingRepository(): Repository
    {
        return $this->repository('missing-project');
    }

    protected function repository(string $name): Repository
    {
        return new Repository([
            'name' => $name,
            'directory' => sprintf('var/repositories/%s', $name),
            'remote' => sprintf('git@gitlab.example.com:team/%s.git', $name),
            'branch' => 'main',
        ]);
    }
}
