<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Unit\Service\Config;

use EvilStudio\ComposerParser\Service\Config\ConfigValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConfigValidatorSecurityTest extends TestCase
{
    protected const string LIST_URL = 'https://example.test/vulnerable-extensions.csv';

    public function testMissingSecurityConfigKeepsConfigurationValid(): void
    {
        $this->validate(null);

        self::assertTrue(true);
    }

    public function testDisabledSecurityDoesNotRequireListsOrHighlight(): void
    {
        $this->validate(['enabled' => false], []);

        self::assertTrue(true);
    }

    public function testEnabledSecurityAcceptsFullConfiguration(): void
    {
        $this->validate($this->enabledSecurity());

        self::assertTrue(true);
    }

    public function testNonBooleanEnabledIsRejected(): void
    {
        $this->expectValidationError('app.config.security.enabled must be a boolean.');

        $this->validate(['enabled' => 'yes']);
    }

    public function testNonBooleanMatchByPackageNameIsRejected(): void
    {
        $this->expectValidationError('app.config.security.matchByPackageName must be a boolean.');

        $this->validate($this->enabledSecurity(['matchByPackageName' => 'yes']));
    }

    public function testEmptyGroupNameIsRejected(): void
    {
        $this->expectValidationError('app.config.security.groupName must be a non-empty string.');

        $this->validate($this->enabledSecurity(['groupName' => '  ']));
    }

    public function testMissingListsIsRejected(): void
    {
        $this->expectValidationError('app.config.security.lists requires at least one entry');

        $this->validate(['enabled' => true]);
    }

    public function testUnsupportedListTypeIsRejected(): void
    {
        $this->expectValidationError('app.config.security.lists must be keyed by one of [mageVulnDb], got "packagist"');

        $this->validate(['enabled' => true, 'lists' => ['packagist' => ['urls' => [self::LIST_URL]]]]);
    }

    public function testListWithoutUrlsIsRejected(): void
    {
        $this->expectValidationError('app.config.security.lists.mageVulnDb.urls requires at least one URL.');

        $this->validate(['enabled' => true, 'lists' => ['mageVulnDb' => ['urls' => []]]]);
    }

    public function testInvalidUrlIsRejected(): void
    {
        $this->expectValidationError('app.config.security.lists.mageVulnDb.urls[0] must be a valid URL.');

        $this->validate(['enabled' => true, 'lists' => ['mageVulnDb' => ['urls' => ['not-a-url']]]]);
    }

    public function testMissingSecurityHighlightIsRejectedForStyledWriter(): void
    {
        $this->expectValidationError('writer.config.styling.securityHighlight is required when security is enabled.');

        $this->validate($this->enabledSecurity(), null);
    }

    public function testInvalidSecurityHighlightColorIsRejected(): void
    {
        $this->expectValidationError('writer.config.styling.securityHighlight.backgroundColor must be a #RRGGBB string.');

        $this->validate($this->enabledSecurity(), ['backgroundColor' => 'CC0000']);
    }

    public function testSecurityHighlightWithoutAnyColorIsRejected(): void
    {
        $this->expectValidationError('writer.config.styling.securityHighlight requires color or backgroundColor.');

        $this->validate($this->enabledSecurity(), ['unrelated' => '#CC0000']);
    }

    public function testSecurityHighlightIsNotRequiredForUnstyledWriter(): void
    {
        $this->validate($this->enabledSecurity(), null, 'json');

        self::assertTrue(true);
    }

    protected function enabledSecurity(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'matchByPackageName' => true,
            'groupName' => 'Security findings',
            'lists' => ['mageVulnDb' => ['urls' => [self::LIST_URL]]],
        ], $overrides);
    }

    protected function expectValidationError(string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
    }

    protected function validate(
        ?array $securityConfig,
        ?array $securityHighlight = ['color' => '#FFFFFF', 'backgroundColor' => '#CC0000'],
        string $writerType = 'xlsx'
    ): void {
        $appConfig = [
            'providerType' => 'gitRepository',
            'parserType' => 'composerJsonAndLock',
            'writerType' => $writerType,
            'timezone' => 'Europe/Warsaw',
        ];

        if ($securityConfig !== null) {
            $appConfig['security'] = $securityConfig;
        }

        $styling = ['groupHeaderBackgroundColor' => '#F2F2F2', 'cellStyleMapping' => []];
        if ($securityHighlight !== null) {
            $styling['securityHighlight'] = $securityHighlight;
        }

        (new ConfigValidator())->validate(
            $appConfig,
            [
                'includeInstalledVersion' => true,
                'installedVersionDisplayedIn' => 'comment',
                'packageGroups' => [
                    ['name' => 'All', 'parserPriority' => 0, 'writerOrder' => 0, 'groupType' => 'require', 'regex' => '/.*/'],
                ],
            ],
            [
                'local' => ['fileName' => 'report-{date}', 'fileDirectory' => 'var/results'],
                'shared' => ['sheetName' => 'Packages in projects'],
                'styling' => $styling,
            ],
            [
                'repositoryList' => [
                    ['name' => 'project-a', 'directory' => 'var/repositories/project-a', 'remote' => 'git@example.com:team/a.git', 'branch' => 'main'],
                ],
            ]
        );
    }
}
