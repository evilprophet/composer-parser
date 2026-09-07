<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Security;

use EvilStudio\ComposerParser\Api\Data\ParsedDataInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryInterface;
use EvilStudio\ComposerParser\Api\Data\RepositoryListInterface;
use EvilStudio\ComposerParser\Api\PackageKeyResolverInterface;
use EvilStudio\ComposerParser\Api\ProviderInterface;
use EvilStudio\ComposerParser\Api\VulnerabilityListInterface;
use EvilStudio\ComposerParser\Exception\RepositoryProcessingException;
use EvilStudio\ComposerParser\Model\ParsedData;
use EvilStudio\ComposerParser\Model\SecurityFinding;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;

class SecurityScanner
{
    protected const string LOCK_PACKAGES_KEY = 'packages';
    protected const string LOCK_PACKAGES_DEV_KEY = 'packages-dev';
    protected const string DEFAULT_GROUP_NAME = 'Security findings';

    protected const string MISSING_LOCK_ERROR = 'Security scanning is enabled but "%s" has no readable composer.lock.';
    protected const string UNSUPPORTED_LIST_ERROR = 'Invalid config: app.config.security list type "%s" is not supported.';

    protected int $matchedByNamespaceCount = 0;
    protected int $matchedByPackageNameCount = 0;
    protected int $advisoryCount = 0;
    protected array $unverifiedComponentKeys = [];

    public function __construct(
        protected RepositoryListInterface $repositoryList,
        protected ProviderManager $providerManager,
        protected VersionComparator $versionComparator,
        protected ContainerInterface $vulnerabilityLists,
        protected ContainerInterface $packageKeyResolvers,
        protected array $securityConfig = []
    ) {
    }

    public function scan(ParsedDataInterface $parsedData): ParsedDataInterface
    {
        if (($this->securityConfig['enabled'] ?? false) !== true) {
            return $parsedData;
        }

        $this->matchedByNamespaceCount = 0;
        $this->matchedByPackageNameCount = 0;
        $this->advisoryCount = 0;
        $this->unverifiedComponentKeys = [];

        $configuredLists = $this->getConfiguredLists();
        $provider = $this->providerManager->getProvider();
        $findings = [];

        foreach ($this->repositoryList->getList() as $repository) {
            try {
                $findings = array_merge($findings, $this->getFindingsForRepository($repository, $provider, $configuredLists));
            } catch (Throwable $throwable) {
                throw new RepositoryProcessingException($repository->getProjectName(), $throwable);
            }
        }

        $projectNames = $parsedData->getProjectNames();

        return new ParsedData(
            $this->addFindingsToGroups($parsedData->getGroups(), $projectNames, $findings),
            $projectNames,
            $findings,
            $this->getSummary($findings)
        );
    }

    protected function getConfiguredLists(): array
    {
        $configuredLists = [];

        foreach (array_keys((array) ($this->securityConfig['lists'] ?? [])) as $type) {
            $type = (string) $type;
            if (!$this->vulnerabilityLists->has($type) || !$this->packageKeyResolvers->has($type)) {
                throw new RuntimeException(sprintf(self::UNSUPPORTED_LIST_ERROR, $type));
            }

            $vulnerabilityList = $this->vulnerabilityLists->get($type);
            $packageKeyResolver = $this->packageKeyResolvers->get($type);
            if (!$vulnerabilityList instanceof VulnerabilityListInterface || !$packageKeyResolver instanceof PackageKeyResolverInterface) {
                throw new RuntimeException(sprintf(self::UNSUPPORTED_LIST_ERROR, $type));
            }

            $entriesByCanonicalKey = $vulnerabilityList->getEntriesByCanonicalKey();
            $this->advisoryCount += count($entriesByCanonicalKey);

            $configuredLists[] = [
                'name' => $vulnerabilityList->getName(),
                'resolver' => $packageKeyResolver,
                'entries' => $entriesByCanonicalKey,
            ];
        }

        return $configuredLists;
    }

    protected function getFindingsForRepository(RepositoryInterface $repository, ProviderInterface $provider, array $configuredLists): array
    {
        $composerLockContent = $provider->getComposerLockContentForRepository($repository);
        if ($composerLockContent === []) {
            throw new RuntimeException(sprintf(self::MISSING_LOCK_ERROR, $repository->getProjectName()));
        }

        $findings = [];

        foreach ([self::LOCK_PACKAGES_KEY, self::LOCK_PACKAGES_DEV_KEY] as $packageListKey) {
            foreach ((array) ($composerLockContent[$packageListKey] ?? []) as $lockPackage) {
                if (!is_array($lockPackage) || !isset($lockPackage['name'])) {
                    continue;
                }

                $packageFindings = [];
                foreach ($configuredLists as $configuredList) {
                    $finding = $this->getFinding($repository->getProjectName(), $lockPackage, $configuredList);
                    if ($finding !== null) {
                        $packageFindings[] = $finding;
                    }
                }

                if ($packageFindings === []) {
                    $this->countUnverifiedComponent($repository->getProjectName(), $lockPackage, $configuredLists);
                    continue;
                }

                $findings = array_merge($findings, $packageFindings);
            }
        }

        unset($composerLockContent);

        return $findings;
    }

    protected function getFinding(string $projectName, array $lockPackage, array $configuredList): ?SecurityFinding
    {
        /** @var PackageKeyResolverInterface $packageKeyResolver */
        $packageKeyResolver = $configuredList['resolver'];
        $entriesByCanonicalKey = $configuredList['entries'];

        $canonicalKeys = $packageKeyResolver->getCanonicalKeysByReliability($lockPackage);
        $matchedKey = null;
        foreach ($canonicalKeys as $canonicalKey) {
            if (isset($entriesByCanonicalKey[$canonicalKey[PackageKeyResolverInterface::KEY_CANONICAL]])) {
                $matchedKey = $canonicalKey;
                break;
            }
        }

        if ($matchedKey === null) {
            return null;
        }

        $entry = $entriesByCanonicalKey[$matchedKey[PackageKeyResolverInterface::KEY_CANONICAL]];
        $installedVersion = (string) ($lockPackage['version'] ?? '');
        $fixedIn = (string) $entry[VulnerabilityListInterface::ENTRY_FIXED_IN];

        $isOlder = $this->versionComparator->isOlderThan($installedVersion, $fixedIn);
        if ($isOlder === false) {
            return null;
        }

        $matchedBy = (string) $matchedKey[PackageKeyResolverInterface::KEY_MATCHED_BY];
        if ($matchedBy === PackageKeyResolverInterface::MATCHED_BY_NAMESPACE) {
            $this->matchedByNamespaceCount++;
        } else {
            $this->matchedByPackageNameCount++;
        }

        return new SecurityFinding(
            $projectName,
            (string) $lockPackage['name'],
            (string) $configuredList['name'],
            (string) $entry[VulnerabilityListInterface::ENTRY_NAME],
            $installedVersion,
            $fixedIn,
            $matchedBy,
            (string) $matchedKey[PackageKeyResolverInterface::KEY_SOURCE],
            $isOlder === true ? SecurityFinding::STATUS_VULNERABLE : SecurityFinding::STATUS_INCONCLUSIVE,
            (bool) $entry[VulnerabilityListInterface::ENTRY_UNCERTAIN],
            (string) $entry[VulnerabilityListInterface::ENTRY_REFERENCE],
            (string) $entry[VulnerabilityListInterface::ENTRY_UPDATE_URL]
        );
    }

    protected function countUnverifiedComponent(string $projectName, array $lockPackage, array $configuredLists): void
    {
        foreach ($configuredLists as $configuredList) {
            /** @var PackageKeyResolverInterface $packageKeyResolver */
            $packageKeyResolver = $configuredList['resolver'];
            if (!$packageKeyResolver->isExpectedToResolve($lockPackage)) {
                continue;
            }

            foreach ($packageKeyResolver->getCanonicalKeysByReliability($lockPackage) as $canonicalKey) {
                if ($canonicalKey[PackageKeyResolverInterface::KEY_MATCHED_BY] === PackageKeyResolverInterface::MATCHED_BY_NAMESPACE) {
                    continue 2;
                }
            }

            $this->unverifiedComponentKeys[sprintf('%s|%s', $projectName, (string) $lockPackage['name'])] = true;

            return;
        }
    }

    protected function addFindingsToGroups(array $groups, array $projectNames, array $findings): array
    {
        $securityGroupName = $this->getSecurityGroupName();
        $emptyPackageRow = array_fill_keys($projectNames, ['value' => '', 'comment' => '']);

        foreach ($findings as $finding) {
            $packageName = $finding->getPackageName();
            $projectName = $finding->getProjectName();
            $note = $this->getFindingNote($finding);
            $addedToExistingGroup = false;

            foreach ($groups as $groupName => $packages) {
                if ($groupName === $securityGroupName || !isset($packages[$packageName][$projectName])) {
                    continue;
                }

                $groups[$groupName][$packageName][$projectName]['comment'] .= $note;
                $addedToExistingGroup = true;
            }

            if ($addedToExistingGroup) {
                continue;
            }

            if (!isset($groups[$securityGroupName][$packageName])) {
                $groups[$securityGroupName][$packageName] = $emptyPackageRow;
            }

            $groups[$securityGroupName][$packageName][$projectName] = [
                'value' => $finding->getInstalledVersion(),
                'comment' => $note,
            ];
        }

        if (isset($groups[$securityGroupName])) {
            ksort($groups[$securityGroupName]);
        }

        return $groups;
    }

    protected function getFindingNote(SecurityFinding $finding): string
    {
        $lines = [sprintf('%s: %s', $finding->getListName(), $finding->getEntryName())];

        if ($finding->getStatus() === SecurityFinding::STATUS_VULNERABLE) {
            $lines[] = sprintf('Installed: %s, fixed in: %s', $finding->getInstalledVersion(), $finding->getFixedIn());
        } else {
            $lines[] = sprintf(
                'Installed: %s, fixed in: %s - versions are not comparable, verify manually',
                $finding->getInstalledVersion() !== '' ? $finding->getInstalledVersion() : 'unknown',
                $finding->getFixedIn() !== '' ? $finding->getFixedIn() : 'unknown'
            );
        }

        $lines[] = sprintf('Matched by: %s (%s)', $finding->getMatchedBy(), $finding->getMatchedSource());

        if ($finding->isUncertainEntry()) {
            $lines[] = 'The source list marks this entry as unconfirmed';
        }

        if ($finding->getReferenceUrl() !== '') {
            $lines[] = sprintf('Reference: %s', $finding->getReferenceUrl());
        }

        if ($finding->getUpdateUrl() !== '') {
            $lines[] = sprintf('Update: %s', $finding->getUpdateUrl());
        }

        return implode("\n", $lines) . "\n";
    }

    protected function getSecurityGroupName(): string
    {
        $securityGroupName = trim((string) ($this->securityConfig['groupName'] ?? ''));

        return $securityGroupName !== '' ? $securityGroupName : self::DEFAULT_GROUP_NAME;
    }

    protected function getSummary(array $findings): string
    {
        $listTypes = array_keys((array) ($this->securityConfig['lists'] ?? []));

        $flaggedProjects = [];
        $flaggedPackages = [];
        foreach ($findings as $finding) {
            $flaggedProjects[$finding->getProjectName()] = true;
            $flaggedPackages[$finding->getPackageName()] = true;
        }

        return implode("\n", [
            sprintf('Security scan (%s, %d advisories, %s)', implode(', ', $listTypes), $this->advisoryCount, date('Y-m-d H:i')),
            sprintf('Flagged: %d packages in %d projects, %d findings', count($flaggedPackages), count($flaggedProjects), count($findings)),
            sprintf('Matched by namespace: %d, by package name: %d', $this->matchedByNamespaceCount, $this->matchedByPackageNameCount),
            sprintf('Components with no key from autoload metadata: %d, not verified', count($this->unverifiedComponentKeys)),
        ]);
    }
}
