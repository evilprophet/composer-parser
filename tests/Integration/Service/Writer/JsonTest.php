<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Service\Writer;

use EvilStudio\ComposerParser\Model\PackageConfig;
use EvilStudio\ComposerParser\Model\ParsedData;
use EvilStudio\ComposerParser\Service\Report\ReportFactory;
use EvilStudio\ComposerParser\Service\Report\ReportValidator;
use EvilStudio\ComposerParser\Service\Writer\Json;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class JsonTest extends TestCase
{
    public function testExecuteWritesJsonFileWithReportData(): void
    {
        $directory = sys_get_temp_dir() . '/composer-parser-json-writer-' . uniqid('', true);
        $fileName = 'test-report';
        $filePath = $directory . DIRECTORY_SEPARATOR . $fileName . '.json';

        $packageConfig = new PackageConfig([
            'packageGroups' => [
                ['name' => 'Group B', 'parserPriority' => 1, 'writerOrder' => 20, 'groupType' => 'require', 'regex' => '/.*/'],
                ['name' => 'Group A', 'parserPriority' => 2, 'writerOrder' => 10, 'groupType' => 'require', 'regex' => '/.*/'],
            ],
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'observedPackages' => [],
        ]);

        $writer = new Json($fileName, $directory, $packageConfig, new ReportFactory(new ReportValidator()));
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

        $payload = json_decode((string) file_get_contents($filePath), true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('generatedAt', $payload);
        self::assertSame(['project-a'], $payload['projects']);
        self::assertSame(['Group A', 'Group B'], array_keys($payload['groups']));
        self::assertSame('^1.0', $payload['groups']['Group B']['vendor/a']['project-a']['value']);
        self::assertSame('Installed version: 1.2.3', $payload['groups']['Group B']['vendor/a']['project-a']['comment']);

        $filesystem = new Filesystem();
        $filesystem->remove($directory);
    }
}
