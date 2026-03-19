<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser;

use EvilStudio\ComposerParser\Command\Cleanup;
use EvilStudio\ComposerParser\Command\Run;
use EvilStudio\ComposerParser\Service\Config\ConfigValidator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class Application extends \Symfony\Component\Console\Application
{
    public function __construct(
        protected ?string $parametersFile = null,
        string $name = 'Composer Parser',
        string $version = '2.0'
    ) {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('app.dir', __DIR__ . '/..');

        $resolvedParametersFile = $this->resolveParametersFile();

        $parametersLoader = new YamlFileLoader($containerBuilder, new FileLocator([dirname($resolvedParametersFile)]));
        $parametersLoader->load(basename($resolvedParametersFile));

        $servicesLoader = new YamlFileLoader($containerBuilder, new FileLocator([__DIR__ . '/../config']));
        $servicesLoader->load('services.yaml');

        $containerBuilder->compile();

        /** @var ConfigValidator $configValidator */
        $configValidator = $containerBuilder->get(ConfigValidator::class);
        $configValidator->validate(
            $containerBuilder->getParameter('app.config'),
            $containerBuilder->getParameter('package.config'),
            $containerBuilder->getParameter('writer.config'),
            $containerBuilder->getParameter('repository.config')
        );
        if ($containerBuilder->getParameter('app.config')['writerType'] === 'googleSheets') {
            $configValidator->validateGoogleSheetsWriterConfig($containerBuilder->getParameter('writer.config'));
        }
        $this->applyGlobalTimezone($containerBuilder->getParameter('app.config'));

        parent::__construct($name, $version);

        $this->addCommands([
            $containerBuilder->get(Run::class),
            $containerBuilder->get(Cleanup::class)
        ]);
    }

    protected function resolveParametersFile(): string
    {
        $parametersFile = $this->parametersFile ?? __DIR__ . '/../config/parameters.yaml';

        if (!$this->isAbsolutePath($parametersFile)) {
            $parametersFile = getcwd() . DIRECTORY_SEPARATOR . $parametersFile;
        }

        if (!is_file($parametersFile)) {
            throw new \InvalidArgumentException(sprintf('Parameters file not found: %s', $parametersFile));
        }

        return $parametersFile;
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || (bool)preg_match('/^[A-Za-z]:\\\\/', $path);
    }

    protected function applyGlobalTimezone(array $appConfig): void
    {
        $timezone = $appConfig['timezone'] ?? 'UTC';
        date_default_timezone_set($timezone);
    }
}
