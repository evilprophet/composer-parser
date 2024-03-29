<?php

namespace EvilStudio\ComposerParser;

use EvilStudio\ComposerParser\Command\Run;
use EvilStudio\ComposerParser\Command\Cleanup;
use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class Application extends \Symfony\Component\Console\Application
{
    public function __construct(string $name = 'Composer Parser', string $version = '2.0')
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('app.dir', __DIR__ . '/..');

        $loader = new YamlFileLoader($containerBuilder, new FileLocator([__DIR__ . '/../config']));
        $loader->load('parameters.yaml');
        $loader->load('services.yaml');

        parent::__construct($name, $version);

        $this->addCommands([
            new Run(
                $containerBuilder->get('parserManager.service'),
                $containerBuilder->get('writerManager.service')
            )
        ]);

        $this->addCommands([
            new Cleanup(
                $containerBuilder->get('cleaner.service')
            )
        ]);
    }

}
