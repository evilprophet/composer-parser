<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Service\Security;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\VulnerabilityListInterface;
use EvilStudio\ComposerParser\Exception\RepositoryProcessingException;
use EvilStudio\ComposerParser\Model\ParsedData;
use EvilStudio\ComposerParser\Model\RepositoryList;
use EvilStudio\ComposerParser\Model\SecurityFinding;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;
use EvilStudio\ComposerParser\Service\Security\ModuleCodeKey;
use EvilStudio\ComposerParser\Service\Security\PackageKey\ComposerPackageName;
use EvilStudio\ComposerParser\Service\Security\PackageKey\MagentoModuleCode;
use EvilStudio\ComposerParser\Service\Security\SecurityScanner;
use EvilStudio\ComposerParser\Service\Security\VersionComparator;
use EvilStudio\ComposerParser\Tests\Integration\Support\InMemoryProvider;
use EvilStudio\ComposerParser\Tests\Integration\Support\InMemoryVulnerabilityList;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;

class SecurityScannerTest extends TestCase
{
    protected const string LIST_TYPE = 'testList';
    protected const string GROUP_NAME = 'Security findings';

    public function testDisabledScannerReturnsParsedDataUntouched(): void
    {
        $parsedData = new ParsedData(['Group' => []], ['project-a']);
        $scanner = $this->createScanner([], [], ['enabled' => false]);

        self::assertSame($parsedData, $scanner->scan($parsedData));
    }

    public function testVulnerablePackageIsFlaggedAndNotedOnItsExistingRow(): void
    {
        $parsedData = $this->scan(
            ['Extensions' => ['amasty/promo' => ['project-a' => ['value' => '2.18.0', 'comment' => '']]]],
            ['amasty/promo' => '2.18.0'],
            ['Amasty_Promo' => '2.19.0']
        );

        $findings = $parsedData->getSecurityFindings();
        self::assertCount(1, $findings);
        self::assertSame(SecurityFinding::STATUS_VULNERABLE, $findings[0]->getStatus());
        self::assertSame('Amasty_Promo', $findings[0]->getEntryName());

        $comment = $parsedData->getGroups()['Extensions']['amasty/promo']['project-a']['comment'];
        self::assertStringContainsString('Amasty_Promo', $comment);
        self::assertStringContainsString('Installed: 2.18.0, fixed in: 2.19.0', $comment);
        self::assertArrayNotHasKey(self::GROUP_NAME, $parsedData->getGroups());
    }

    public function testPatchedPackageProducesNoFindingAndNoNote(): void
    {
        $parsedData = $this->scan(
            ['Extensions' => ['amasty/promo' => ['project-a' => ['value' => '2.19.0', 'comment' => '']]]],
            ['amasty/promo' => '2.19.0'],
            ['Amasty_Promo' => '2.19.0']
        );

        self::assertSame([], $parsedData->getSecurityFindings());
        self::assertSame('', $parsedData->getGroups()['Extensions']['amasty/promo']['project-a']['comment']);
    }

    public function testUnmatchedPackageProducesNoFinding(): void
    {
        $parsedData = $this->scan(
            ['Extensions' => ['symfony/finder' => ['project-a' => ['value' => '7.0.0', 'comment' => '']]]],
            ['symfony/finder' => '7.0.0'],
            ['Amasty_Finder' => '9.9.9']
        );

        self::assertSame([], $parsedData->getSecurityFindings());
        self::assertSame('', $parsedData->getGroups()['Extensions']['symfony/finder']['project-a']['comment']);
    }

    public function testTransitivePackageWithoutRowGoesToTheSyntheticGroup(): void
    {
        $parsedData = $this->scan(
            ['Extensions' => []],
            ['amasty/promo' => '2.18.0'],
            ['Amasty_Promo' => '2.19.0']
        );

        $groups = $parsedData->getGroups();
        self::assertSame('2.18.0', $groups[self::GROUP_NAME]['amasty/promo']['project-a']['value']);
        self::assertStringContainsString('Amasty_Promo', $groups[self::GROUP_NAME]['amasty/promo']['project-a']['comment']);
    }

    public function testSyntheticGroupCarriesACellForEveryProject(): void
    {
        $provider = new InMemoryProvider([
            'project-a' => ['composerLock' => ['packages' => [$this->lockPackage('amasty/promo', '2.18.0')]]],
            'project-b' => ['composerLock' => ['packages' => []]],
        ]);

        $parsedData = $this->createScanner(
            ['Amasty_Promo' => '2.19.0'],
            ['project-a', 'project-b'],
            $this->enabledConfig(),
            $this->createRepositoryList(['project-a', 'project-b']),
            $provider
        )->scan(new ParsedData(['Extensions' => []], ['project-a', 'project-b']));

        $packageRow = $parsedData->getGroups()[self::GROUP_NAME]['amasty/promo'];
        self::assertSame('2.18.0', $packageRow['project-a']['value']);
        self::assertSame('', $packageRow['project-b']['value']);
    }

    public function testDevVersionIsFlaggedAsInconclusive(): void
    {
        $parsedData = $this->scan(
            ['Extensions' => ['amasty/promo' => ['project-a' => ['value' => 'dev-master', 'comment' => '']]]],
            ['amasty/promo' => 'dev-master'],
            ['Amasty_Promo' => '2.19.0']
        );

        $findings = $parsedData->getSecurityFindings();
        self::assertSame(SecurityFinding::STATUS_INCONCLUSIVE, $findings[0]->getStatus());
        self::assertStringContainsString(
            'versions are not comparable, verify manually',
            $parsedData->getGroups()['Extensions']['amasty/promo']['project-a']['comment']
        );
    }

    public function testTwoPackagesMappingToTheSameModuleAreBothEvaluated(): void
    {
        $parsedData = $this->scan(
            ['Extensions' => []],
            ['fooman/pdfcustomiser-m2' => '8.0.0', 'fooman/pdfcustomiser-implementation-m2' => '118.0.3'],
            ['Fooman_PdfCustomiser' => '8.1.8'],
            ['project-a'],
            [
                'fooman/pdfcustomiser-m2' => ['Fooman\\PdfCustomiser\\' => ''],
                'fooman/pdfcustomiser-implementation-m2' => ['Fooman\\PdfCustomiser\\' => ''],
            ]
        );

        $flaggedPackages = array_map(
            static fn (SecurityFinding $finding): string => $finding->getPackageName(),
            $parsedData->getSecurityFindings()
        );

        self::assertSame(['fooman/pdfcustomiser-m2'], $flaggedPackages);
    }

    public function testFindingsAreCollectedForEveryProject(): void
    {
        $repositoryList = $this->createRepositoryList(['project-a', 'project-b']);
        $provider = new InMemoryProvider([
            'project-a' => ['composerLock' => ['packages' => [$this->lockPackage('amasty/promo', '2.18.0')]]],
            'project-b' => ['composerLock' => ['packages' => [$this->lockPackage('amasty/promo', '2.19.0')]]],
        ]);

        $parsedData = $this->createScanner(
            ['Amasty_Promo' => '2.19.0'],
            ['project-a', 'project-b'],
            $this->enabledConfig(),
            $repositoryList,
            $provider
        )->scan(new ParsedData(['Extensions' => []], ['project-a', 'project-b']));

        $findings = $parsedData->getSecurityFindings();
        self::assertCount(1, $findings);
        self::assertSame('project-a', $findings[0]->getProjectName());
    }

    public function testMissingComposerLockStopsTheRun(): void
    {
        $scanner = $this->createScanner(
            ['Amasty_Promo' => '2.19.0'],
            ['project-a'],
            $this->enabledConfig(),
            $this->createRepositoryList(['project-a']),
            new InMemoryProvider([])
        );

        $this->expectException(RepositoryProcessingException::class);
        $this->expectExceptionMessage('project-a');

        $scanner->scan(new ParsedData(['Extensions' => []], ['project-a']));
    }

    public function testUnsupportedListTypeStopsTheRun(): void
    {
        $scanner = new SecurityScanner(
            $this->createRepositoryList(['project-a']),
            $this->createProviderManager(new InMemoryProvider([])),
            new VersionComparator(),
            new ServiceLocator([]),
            new ServiceLocator([]),
            ['enabled' => true, 'lists' => ['missingList' => ['urls' => ['https://example.test/list.csv']]]]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('app.config.security list type "missingList" is not supported');

        $scanner->scan(new ParsedData(['Extensions' => []], ['project-a']));
    }

    public function testSummaryReportsCountsAndUnscannedComponents(): void
    {
        $repositoryList = $this->createRepositoryList(['project-a']);
        $provider = new InMemoryProvider([
            'project-a' => ['composerLock' => ['packages' => [
                $this->lockPackage('amasty/promo', '2.18.0', ['Amasty\\Promo\\' => '']),
                ['name' => 'vendor/broken-module', 'version' => '1.0.0', 'type' => 'magento2-module', 'autoload' => ['psr-4' => ['Vendor\\' => 'src/']]],
            ]]],
        ]);

        $summary = $this->createScanner(
            ['Amasty_Promo' => '2.19.0'],
            ['project-a'],
            $this->enabledConfig(),
            $repositoryList,
            $provider
        )->scan(new ParsedData(['Extensions' => []], ['project-a']))->getSecuritySummary();

        self::assertStringContainsString('1 advisories', $summary);
        self::assertStringContainsString('Flagged: 1 packages in 1 projects, 1 findings', $summary);
        self::assertStringContainsString('Matched by namespace: 1, by package name: 0', $summary);
        self::assertStringContainsString('Components with no key from autoload metadata: 1, not verified', $summary);
    }

    public function testTheSameScannerWorksWithAComposerPackageNameList(): void
    {
        $repositoryList = $this->createRepositoryList(['project-a']);
        $provider = new InMemoryProvider([
            'project-a' => ['composerLock' => ['packages' => [$this->lockPackage('symfony/http-kernel', '6.0.0')]]],
        ]);

        $scanner = new SecurityScanner(
            $repositoryList,
            $this->createProviderManager($provider),
            new VersionComparator(),
            new ServiceLocator([self::LIST_TYPE => static fn (): VulnerabilityListInterface => new InMemoryVulnerabilityList('PackagistAdvisories', [
                'symfony/http-kernel' => [
                    VulnerabilityListInterface::ENTRY_NAME => 'symfony/http-kernel',
                    VulnerabilityListInterface::ENTRY_FIXED_IN => '6.0.1',
                    VulnerabilityListInterface::ENTRY_REFERENCE => '',
                    VulnerabilityListInterface::ENTRY_UPDATE_URL => '',
                    VulnerabilityListInterface::ENTRY_UNCERTAIN => false,
                ],
            ])]),
            new ServiceLocator([self::LIST_TYPE => static fn (): ComposerPackageName => new ComposerPackageName()]),
            $this->enabledConfig()
        );

        $findings = $scanner->scan(new ParsedData(['Extensions' => []], ['project-a']))->getSecurityFindings();

        self::assertCount(1, $findings);
        self::assertSame('symfony/http-kernel', $findings[0]->getEntryName());
        self::assertSame('PackagistAdvisories', $findings[0]->getListName());
    }

    protected function scan(
        array $groups,
        array $lockPackageVersions,
        array $listEntries,
        array $projectNames = ['project-a'],
        array $autoloadByPackageName = []
    ): ParsedDataInterface {
        $lockPackages = [];
        foreach ($lockPackageVersions as $packageName => $version) {
            $lockPackages[] = $this->lockPackage($packageName, $version, $autoloadByPackageName[$packageName] ?? null);
        }

        $provider = new InMemoryProvider(array_fill_keys(
            $projectNames,
            ['composerLock' => ['packages' => $lockPackages]]
        ));

        return $this->createScanner(
            $listEntries,
            $projectNames,
            $this->enabledConfig(),
            $this->createRepositoryList($projectNames),
            $provider
        )->scan(new ParsedData($groups, $projectNames));
    }

    protected function createScanner(
        array $listEntries,
        array $projectNames,
        array $securityConfig,
        ?RepositoryList $repositoryList = null,
        ?InMemoryProvider $provider = null
    ): SecurityScanner {
        $entriesByCanonicalKey = [];
        foreach ($listEntries as $moduleCode => $fixedIn) {
            $entriesByCanonicalKey[ModuleCodeKey::canonicalize((string) $moduleCode)] = [
                VulnerabilityListInterface::ENTRY_NAME => (string) $moduleCode,
                VulnerabilityListInterface::ENTRY_FIXED_IN => (string) $fixedIn,
                VulnerabilityListInterface::ENTRY_REFERENCE => '',
                VulnerabilityListInterface::ENTRY_UPDATE_URL => '',
                VulnerabilityListInterface::ENTRY_UNCERTAIN => false,
            ];
        }

        return new SecurityScanner(
            $repositoryList ?? $this->createRepositoryList($projectNames ?: ['project-a']),
            $this->createProviderManager($provider ?? new InMemoryProvider([])),
            new VersionComparator(),
            new ServiceLocator([self::LIST_TYPE => static fn (): VulnerabilityListInterface => new InMemoryVulnerabilityList('TestList', $entriesByCanonicalKey)]),
            new ServiceLocator([self::LIST_TYPE => static fn (): MagentoModuleCode => new MagentoModuleCode()]),
            $securityConfig
        );
    }

    protected function enabledConfig(): array
    {
        return [
            'enabled' => true,
            'groupName' => self::GROUP_NAME,
            'lists' => [self::LIST_TYPE => ['urls' => ['https://example.test/list.csv']]],
        ];
    }

    protected function createRepositoryList(array $projectNames): RepositoryList
    {
        $repositories = [];
        foreach ($projectNames as $projectName) {
            $repositories[] = [
                'name' => $projectName,
                'directory' => sprintf('var/repositories/%s', $projectName),
                'remote' => sprintf('git@gitlab.example.com:team/%s.git', $projectName),
                'branch' => 'main',
            ];
        }

        return new RepositoryList($repositories);
    }

    protected function createProviderManager(InMemoryProvider $provider): ProviderManager
    {
        return new ProviderManager('in-memory', new ServiceLocator(['in-memory' => static fn (): InMemoryProvider => $provider]));
    }

    protected function lockPackage(string $packageName, string $version, ?array $psr4 = null): array
    {
        $lockPackage = ['name' => $packageName, 'version' => $version, 'type' => 'magento2-module'];
        if ($psr4 !== null) {
            $lockPackage['autoload'] = ['psr-4' => $psr4];
        }

        return $lockPackage;
    }
}
