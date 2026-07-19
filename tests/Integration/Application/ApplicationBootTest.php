<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Tests\Integration\Application;

use EvilStudio\ComposerParser\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ApplicationBootTest extends TestCase
{
    public function testDefaultTemplateCanListCommandsWithoutExternalCredentials(): void
    {
        $application = $this->application($this->templatePath());
        $output = new BufferedOutput();

        $exitCode = $application->run(new ArrayInput(['command' => 'list', '--no-ansi' => true]), $output);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('app:run', $output->fetch());
    }

    public function testListDoesNotValidateRuntimeConfiguration(): void
    {
        $parametersFile = tempnam(sys_get_temp_dir(), 'composer-parser-parameters-');
        self::assertNotFalse($parametersFile);

        $template = file_get_contents($this->templatePath());
        self::assertNotFalse($template);
        file_put_contents($parametersFile, str_replace('providerType: gitRepository', 'providerType: unsupported', $template));

        try {
            $application = $this->application($parametersFile);
            $output = new BufferedOutput();

            $exitCode = $application->run(new ArrayInput(['command' => 'list', '--no-ansi' => true]), $output);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('app:cleanup', $output->fetch());
        } finally {
            unlink($parametersFile);
        }
    }

    public function testHelpCanLoadACommandWhenConfigurationSectionsAreAbsent(): void
    {
        $parametersFile = tempnam(sys_get_temp_dir(), 'composer-parser-parameters-');
        self::assertNotFalse($parametersFile);
        file_put_contents($parametersFile, "parameters: {}\n");

        try {
            $application = $this->application($parametersFile);
            $output = new BufferedOutput();

            $exitCode = $application->run(new ArrayInput([
                'command' => 'help',
                'command_name' => 'app:run',
                '--no-ansi' => true,
            ]), $output);

            self::assertSame(Command::SUCCESS, $exitCode);
            $display = $output->fetch();
            self::assertStringContainsString('app:run', $display);
            self::assertStringContainsString('--parameters-file', $display);
            self::assertStringContainsString('-p', $display);
        } finally {
            unlink($parametersFile);
        }
    }

    public function testVersionDoesNotRequireConfigurationSections(): void
    {
        $parametersFile = tempnam(sys_get_temp_dir(), 'composer-parser-parameters-');
        self::assertNotFalse($parametersFile);
        file_put_contents($parametersFile, "parameters: {}\n");

        try {
            $application = $this->application($parametersFile);
            $output = new BufferedOutput();

            $exitCode = $application->run(new ArrayInput(['--version' => true]), $output);

            self::assertSame(Command::SUCCESS, $exitCode);
            self::assertStringContainsString('Composer Parser', $output->fetch());
        } finally {
            unlink($parametersFile);
        }
    }

    public function testCommandLoaderKeepsReportCommandsLazy(): void
    {
        $application = $this->application($this->templatePath());

        self::assertInstanceOf(LazyCommand::class, $application->get('app:run'));
        self::assertInstanceOf(LazyCommand::class, $application->get('app:cleanup'));
    }

    protected function application(string $parametersFile): Application
    {
        $application = new Application($parametersFile);
        $application->setAutoExit(false);

        return $application;
    }

    protected function templatePath(): string
    {
        return dirname(__DIR__, 3) . '/config/parameters.yaml.template';
    }
}
