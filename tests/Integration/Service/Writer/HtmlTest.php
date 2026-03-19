<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Service\Writer;

use EvilStudio\ComposerParser\Model\PackageConfig;
use EvilStudio\ComposerParser\Model\ParsedData;
use EvilStudio\ComposerParser\Model\StylingConfig;
use EvilStudio\ComposerParser\Service\Report\ReportFactory;
use EvilStudio\ComposerParser\Service\Report\ReportValidator;
use EvilStudio\ComposerParser\Service\Writer\Html;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class HtmlTest extends TestCase
{
    public function testExecuteWritesReadableHtmlReport(): void
    {
        $directory = sys_get_temp_dir() . '/composer-parser-html-writer-' . uniqid('', true);
        $fileName = 'test-report';
        $filePath = $directory . DIRECTORY_SEPARATOR . $fileName . '.html';

        $packageConfig = new PackageConfig([
            'packageGroups' => [
                ['name' => 'Group B', 'parserPriority' => 1, 'writerOrder' => 20, 'groupType' => 'require', 'regex' => '/.*/'],
                ['name' => 'Group A', 'parserPriority' => 2, 'writerOrder' => 10, 'groupType' => 'require', 'regex' => '/.*/'],
            ],
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'observedPackages' => [],
        ]);
        $stylingConfig = new StylingConfig([
            'groupHeaderBackgroundColor' => '999999',
            'cellStyleMapping' => [
                ['versionRegex' => '/\^1\.0/', 'color' => 'FF0000', 'backgroundColor' => 'FFFF00'],
            ],
        ]);

        $writer = new Html(
            $fileName,
            $directory,
            $packageConfig,
            $stylingConfig,
            new ReportFactory(new ReportValidator())
        );
        $parsedData = new ParsedData(
            [
                'Group B' => [
                    'vendor/a' => [
                        'project-a' => ['value' => '^1.0', 'comment' => 'Installed version: 1.2.3'],
                    ],
                ],
                'Group A' => [
                    'vendor/b' => [
                        'project-a' => ['value' => '^2.0', 'comment' => ''],
                    ],
                ],
            ],
            ['project-a']
        );

        $writer->execute($parsedData);

        self::assertFileExists($filePath);
        $content = (string) file_get_contents($filePath);
        self::assertStringContainsString('<h1>Composer Parser Report</h1>', $content);
        self::assertStringContainsString('position: sticky', $content);
        self::assertStringContainsString('th.col-package { position: sticky; left: 0;', $content);
        self::assertStringContainsString('COMMENT', $content);
        self::assertStringContainsString('background:#FFFF00', $content);
        self::assertStringContainsString('vendor/a', $content);
        self::assertStringContainsString('Installed version: 1.2.3', $content);
        self::assertLessThan(strpos($content, 'Group B'), strpos($content, 'Group A'));

        $filesystem = new Filesystem();
        $filesystem->remove($directory);
    }
}
