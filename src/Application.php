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
    public function __construct(string $name = 'Composer Parser', string $version = '2.0')
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('app.dir', __DIR__ . '/..');

        $loader = new YamlFileLoader($containerBuilder, new FileLocator([__DIR__ . '/../config']));
        $loader->load('parameters.yaml');
        $loader->load('services.yaml');

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

        parent::__construct($name, $version);

        $this->addCommands([
            $containerBuilder->get(Run::class),
            $containerBuilder->get(Cleanup::class)
        ]);
    }
}
