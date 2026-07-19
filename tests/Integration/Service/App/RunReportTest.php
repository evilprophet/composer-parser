<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Service\App;

use EvilStudio\ComposerParser\Api\ParserInterface;
use EvilStudio\ComposerParser\Exception\RepositoryProcessingException;
use EvilStudio\ComposerParser\Model\PackageConfig;
use EvilStudio\ComposerParser\Model\RepositoryList;
use EvilStudio\ComposerParser\Service\App\RunReport;
use EvilStudio\ComposerParser\Service\Parser\ComposerJson;
use EvilStudio\ComposerParser\Service\Parser\ParserManager;
use EvilStudio\ComposerParser\Service\Parser\RepositoryDataFactory;
use EvilStudio\ComposerParser\Service\Provider\ProviderManager;
use EvilStudio\ComposerParser\Service\Writer\WriterManager;
use EvilStudio\ComposerParser\Tests\Integration\Support\CapturingWriter;
use EvilStudio\ComposerParser\Tests\Integration\Support\InMemoryProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;

class RunReportTest extends TestCase
{
    public function testExecuteRunsParserAndPassesDataToWriter(): void
    {
        $packageConfig = new PackageConfig([
            'includeInstalledVersion' => false,
            'installedVersionDisplayedIn' => 'comment',
            'packageGroups' => [
                ['name' => 'Core', 'parserPriority' => 10, 'writerOrder' => 10, 'groupType' => 'require', 'regex' => '/^vendor\/a$/'],
                ['name' => 'Framework', 'parserPriority' => 9, 'writerOrder' => 11, 'groupType' => 'require', 'regex' => '/^vendor\/framework$/'],
                ['name' => 'Other', 'parserPriority' => 1, 'writerOrder' => 20, 'groupType' => 'require', 'regex' => '/.*/'],
                ['name' => 'Require Dev', 'parserPriority' => 1, 'writerOrder' => 30, 'groupType' => 'require-dev', 'regex' => '/.*/'],
            ],
            'observedPackages' => [],
        ]);

        $repositoryList = new RepositoryList([
            [
                'name' => 'project-a',
                'directory' => 'var/repositories/project-a',
                'remote' => 'git@gitlab.example.com:team/project-a.git',
                'branch' => 'main',
            ],
            [
                'name' => 'project-b',
                'directory' => 'var/repositories/project-b',
                'remote' => 'git@gitlab.example.com:team/project-b.git',
                'branch' => 'main',
            ],
        ]);

        $provider = new InMemoryProvider([
            'project-a' => [
                'composerJson' => [
                    'require' => [
                        'vendor/a' => '^1.0',
                        'vendor/framework' => '^9.2',
                        'vendor/cache' => '^3.1',
                        'vendor/http' => '^7.8',
                        'vendor/log' => '^2.0',
                    ],
                    'require-dev' => [
                        'vendor/dev-tool' => '^3.0',
                    ],
                ],
            ],
            'project-b' => [
                'composerJson' => [
                    'require' => [
                        'vendor/a' => '^1.1',
                        'vendor/framework' => '^9.3',
                        'vendor/cache' => '^3.2',
                        'vendor/http' => '^7.9',
                        'vendor/log' => '^2.1',
                    ],
                    'require-dev' => [
                        'vendor/dev-tool' => '^3.1',
                    ],
                ],
            ],
        ]);

        $providerManager = new ProviderManager('in-memory', new ServiceLocator([
            'in-memory' => static fn () => $provider,
        ]));
        $parser = new ComposerJson($packageConfig, $repositoryList, $providerManager, new RepositoryDataFactory());
        $parserManager = new ParserManager('composerJson', new ServiceLocator([
            'composerJson' => static fn () => $parser,
        ]));

        $writer = new CapturingWriter();
        $writerManager = new WriterManager('capture', new ServiceLocator([
            'capture' => static fn () => $writer,
        ]));

        $runReport = new RunReport($parserManager, $writerManager);
        $runReport->execute();

        $captured = $writer->getCapturedParsedData();
        self::assertNotNull($captured);

        $projects = $captured->getProjectNames();
        $data = $captured->getGroups();

        self::assertSame(['project-a', 'project-b'], $projects);
        self::assertSame('^1.0', $data['Core']['vendor/a']['project-a']['value']);
        self::assertSame('^1.1', $data['Core']['vendor/a']['project-b']['value']);
        self::assertSame('^9.2', $data['Framework']['vendor/framework']['project-a']['value']);
        self::assertSame('^9.3', $data['Framework']['vendor/framework']['project-b']['value']);
        self::assertSame('^3.0', $data['Require Dev']['vendor/dev-tool']['project-a']['value']);
        self::assertSame('^3.1', $data['Require Dev']['vendor/dev-tool']['project-b']['value']);
    }

    public function testExecuteDoesNotInvokeWriterWhenParserFails(): void
    {
        $failure = new RepositoryProcessingException(
            'project-b',
            new RuntimeException('Repository data is unavailable.')
        );
        $parser = $this->createStub(ParserInterface::class);
        $parser->method('execute')->willThrowException($failure);
        $parserManager = new ParserManager('failing', new ServiceLocator([
            'failing' => static fn () => $parser,
        ]));

        $writer = new CapturingWriter();
        $writerManager = new WriterManager('capture', new ServiceLocator([
            'capture' => static fn () => $writer,
        ]));

        $runReport = new RunReport($parserManager, $writerManager);

        try {
            $runReport->execute();
            self::fail('Expected report execution to fail.');
        } catch (RepositoryProcessingException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertNull($writer->getCapturedParsedData());
    }
}
