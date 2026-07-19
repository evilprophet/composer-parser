<?php

declare(strict_types=1);

namespace EvilStudio\ComposerParser\Service\Config;

use InvalidArgumentException;

class RuntimeConfigValidator
{
    public function __construct(
        protected ConfigValidator $configValidator,
        protected mixed $appConfig,
        protected mixed $packageConfig,
        protected mixed $writerConfig,
        protected mixed $repositoryConfig
    ) {
    }

    public function validateForRun(): void
    {
        $appConfig = $this->requireArray($this->appConfig, 'app.config');
        $packageConfig = $this->requireArray($this->packageConfig, 'package.config');
        $writerConfig = $this->requireArray($this->writerConfig, 'writer.config');
        $repositoryConfig = $this->requireArray($this->repositoryConfig, 'repository.config');

        $this->configValidator->validate($appConfig, $packageConfig, $writerConfig, $repositoryConfig);
        if ($appConfig['writerType'] === 'googleSheets') {
            $this->configValidator->validateGoogleSheetsWriterConfig($writerConfig);
        }

        date_default_timezone_set($appConfig['timezone']);
    }

    public function validateForCleanup(): void
    {
        $this->configValidator->validateRepositoryConfig(
            $this->requireArray($this->repositoryConfig, 'repository.config')
        );
    }

    protected function requireArray(mixed $config, string $name): array
    {
        if (!is_array($config)) {
            throw new InvalidArgumentException(sprintf('Invalid config: %s must be an array.', $name));
        }

        return $config;
    }
}
