<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Config;

use EvilStudio\ComposerParser\Api\Data\PackageConfigInterface;
use EvilStudio\ComposerParser\Service\Config\ConfigValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConfigValidatorConsistencyTest extends TestCase
{
    public function testValidateRejectsMissingPackageGroupParserPriority(): void
    {
        $packageConfig = $this->packageConfig();
        unset($packageConfig['packageGroups'][0]['parserPriority']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('package.config.packageGroups[0].parserPriority');

        $this->validate($packageConfig);
    }

    public function testValidateRejectsNonIntegerPackageGroupWriterOrder(): void
    {
        $packageConfig = $this->packageConfig();
        $packageConfig['packageGroups'][0]['writerOrder'] = '10';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('package.config.packageGroups[0].writerOrder');

        $this->validate($packageConfig);
    }

    public function testValidateAcceptsAllSupportedPackageGroupTypes(): void
    {
        foreach (
            [
            PackageConfigInterface::COMPOSER_TYPE_REQUIRE,
            PackageConfigInterface::COMPOSER_TYPE_REQUIRE_DEV,
            PackageConfigInterface::COMPOSER_TYPE_REPLACE,
            PackageConfigInterface::COMPOSER_TYPE_PATCHSET,
            PackageConfigInterface::COMPOSER_TYPE_OBSERVED,
            ] as $groupType
        ) {
            $packageConfig = $this->packageConfig();
            $packageConfig['packageGroups'][0]['groupType'] = $groupType;

            if ($groupType === PackageConfigInterface::COMPOSER_TYPE_OBSERVED) {
                $packageConfig['includeInstalledVersion'] = true;
                $this->validate($packageConfig, null, 'composerJsonAndLock');
                continue;
            }

            $this->validate($packageConfig);
        }

        self::assertTrue(true);
    }

    public function testValidateRejectsUnsupportedPackageGroupType(): void
    {
        $packageConfig = $this->packageConfig();
        $packageConfig['packageGroups'][0]['groupType'] = 'require_dev';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('package.config.packageGroups[0].groupType');

        $this->validate($packageConfig);
    }

    public function testValidateRejectsDuplicateRepositoryName(): void
    {
        $repositoryConfig = $this->repositoryConfig();
        $repositoryConfig['repositoryList'][] = [
            'name' => 'project-a',
            'directory' => 'var/repositories/project-b',
            'remote' => 'git@gitlab.example.com:team/project-b.git',
            'branch' => 'main',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('repository.config.repositoryList[1].name');

        $this->validate($this->packageConfig(), $repositoryConfig);
    }

    public function testValidateRejectsDuplicateRepositoryDirectory(): void
    {
        $repositoryConfig = $this->repositoryConfig();
        $repositoryConfig['repositoryList'][] = [
            'name' => 'project-b',
            'directory' => 'var/repositories/project-a',
            'remote' => 'git@gitlab.example.com:team/project-b.git',
            'branch' => 'main',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('repository.config.repositoryList[1].directory');

        $this->validate($this->packageConfig(), $repositoryConfig);
    }

    public function testValidateRejectsIntegerLikeRepositoryName(): void
    {
        $repositoryConfig = $this->repositoryConfig();
        $repositoryConfig['repositoryList'][0]['name'] = '123';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('repository.config.repositoryList[0].name must not be an integer-like string');

        $this->validate($this->packageConfig(), $repositoryConfig);
    }

    public function testValidateRejectsMalformedObservedPackages(): void
    {
        foreach (
            [
            'scalar' => 'vendor/package-a',
            'non-string item' => ['vendor/package-a', 42],
            'blank item' => [' '],
            ] as $description => $observedPackages
        ) {
            $packageConfig = $this->packageConfig();
            $packageConfig['observedPackages'] = $observedPackages;

            try {
                $this->validate($packageConfig);
                self::fail(sprintf('Expected %s observedPackages configuration to be rejected.', $description));
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('package.config.observedPackages', $exception->getMessage());
            }
        }
    }

    public function testValidateTreatsMissingOrNullObservedPackagesAsEmpty(): void
    {
        $missingObservedPackages = $this->packageConfig();
        unset($missingObservedPackages['observedPackages']);
        $this->validate($missingObservedPackages);

        $nullObservedPackages = $this->packageConfig();
        $nullObservedPackages['observedPackages'] = null;
        $this->validate($nullObservedPackages);

        self::assertTrue(true);
    }

    public function testValidateRejectsObservedGroupForComposerJsonParser(): void
    {
        $packageConfig = $this->observedPackageConfig();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('groupType=observed is not supported by parserType=composerJson');

        $this->validate($packageConfig);
    }

    public function testValidateRejectsObservedGroupWithoutInstalledVersions(): void
    {
        $packageConfig = $this->observedPackageConfig();
        $packageConfig['includeInstalledVersion'] = false;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('groupType=observed requires package.config.includeInstalledVersion=true');

        $this->validate($packageConfig, null, 'composerJsonAndLock');
    }

    public function testValidateRejectsObservedGroupWithoutObservedPackages(): void
    {
        $packageConfig = $this->observedPackageConfig();
        $packageConfig['observedPackages'] = [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('groupType=observed requires at least one package.config.observedPackages entry');

        $this->validate($packageConfig, null, 'composerJsonAndLock');
    }

    public function testValidateAcceptsConsistentObservedGroup(): void
    {
        $this->validate($this->observedPackageConfig(), null, 'composerFull');

        self::assertTrue(true);
    }

    protected function validate(array $packageConfig, ?array $repositoryConfig = null, string $parserType = 'composerJson'): void
    {
        (new ConfigValidator())->validate(
            [
                'providerType' => 'gitRepository',
                'parserType' => $parserType,
                'writerType' => 'json',
                'timezone' => 'Europe/Warsaw',
            ],
            $packageConfig,
            [
                'local' => [
                    'fileName' => 'report-{date}',
                    'fileDirectory' => 'var/results',
                ],
                'shared' => [
                    'sheetName' => 'Packages in projects',
                ],
            ],
            $repositoryConfig ?? $this->repositoryConfig()
        );
    }

    protected function packageConfig(): array
    {
        return [
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                [
                    'name' => 'All',
                    'parserPriority' => 0,
                    'writerOrder' => 0,
                    'groupType' => PackageConfigInterface::COMPOSER_TYPE_REQUIRE,
                    'regex' => '/.*/',
                ],
            ],
            'observedPackages' => ['vendor/package-a'],
        ];
    }

    protected function observedPackageConfig(): array
    {
        $packageConfig = $this->packageConfig();
        $packageConfig['includeInstalledVersion'] = true;
        $packageConfig['packageGroups'][0]['groupType'] = PackageConfigInterface::COMPOSER_TYPE_OBSERVED;

        return $packageConfig;
    }

    protected function repositoryConfig(): array
    {
        return [
            'repositoryList' => [
                [
                    'name' => 'project-a',
                    'directory' => 'var/repositories/project-a',
                    'remote' => 'git@gitlab.example.com:team/project-a.git',
                    'branch' => 'main',
                ],
            ],
        ];
    }
}
