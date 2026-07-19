<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class Application extends \Symfony\Component\Console\Application
{
    public function __construct(
        protected ?string $parametersFile = null,
        string $name = 'Composer Parser',
        string $version = '2.0'
    ) {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('app.dir', __DIR__ . '/..');
        foreach (['app.config', 'package.config', 'writer.config', 'repository.config'] as $parameter) {
            $containerBuilder->setParameter($parameter, []);
        }

        $resolvedParametersFile = $this->resolveParametersFile();

        $parametersLoader = new YamlFileLoader($containerBuilder, new FileLocator([dirname($resolvedParametersFile)]));
        $parametersLoader->load(basename($resolvedParametersFile));

        $servicesLoader = new YamlFileLoader($containerBuilder, new FileLocator([__DIR__ . '/../config']));
        $servicesLoader->load('services.yaml');
        $containerBuilder->addCompilerPass(new AddConsoleCommandPass());

        $containerBuilder->compile();

        parent::__construct($name, $version);

        $this->setCommandLoader($containerBuilder->get('console.command_loader'));
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

    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();
        $definition->addOption(new InputOption(
            '--parameters-file',
            '-p',
            InputOption::VALUE_REQUIRED,
            'Parameters YAML file; relative paths are resolved from the current working directory.'
        ));

        return $definition;
    }
}
