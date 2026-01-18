<?php

declare(strict_types=1);

namespace Jax;

use function array_merge;
use function json_encode;

use const JSON_PRETTY_PRINT;

final class ServiceConfig
{
    /**
     * @var array<mixed>
     */
    private array $serviceConfig = [];

    /**
     * @var array<mixed>
     */
    private array $overrideConfig = [];

    private bool $installed = false;

    /**
     * @param array<mixed> $config
     */
    public function __construct(
        private readonly FileSystem $fileSystem,
        ?array $config = null,
    ) {
        $this->installed = $config !== null ? true : $this->hasInstalled();
        $this->serviceConfig = $config ?? $this->getServiceConfig();
    }

    /**
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return array_merge($this->getServiceConfig(), $this->override());
    }

    /**
     * @return array<string,mixed>
     */
    public function getServiceConfig(): array
    {
        if ($this->serviceConfig) {
            return $this->serviceConfig;
        }


        $this->serviceConfig = require_once $this->installed
            ? $this->fileSystem->pathFromRoot('config.php')
            : $this->fileSystem->pathFromRoot('config.default.php');


        return $this->serviceConfig;
    }

    public function hasInstalled(): bool
    {
        return $this->installed || $this->fileSystem->getFileInfo('config.php')->isFile();
    }

    public function getSetting(string $key): mixed
    {
        $config = $this->get();

        return $config[$key] ?? null;
    }

    /**
     * @param array<string,mixed> $override
     *
     * @return array<string,mixed>
     */
    public function override(?array $override = null): array
    {
        if ($override) {
            $this->overrideConfig = $override;
        }

        return $this->overrideConfig;
    }

    /**
     * Write service config during installation.
     *
     * @param array<string,mixed> $data
     */
    public function writeServiceConfig(array $data): void
    {
        $this->serviceConfig = array_merge($this->serviceConfig, $data);
        $this->fileSystem->putContents('config.php', $this->configFileContents($data));
    }

    /**
     * @param array<string,mixed> $data
     */
    public function configFileContents(array $data): string
    {
        $dataString = json_encode($data, JSON_PRETTY_PRINT);

        return <<<EOT
            <?php
            return json_decode(
            <<<'JSON'
            {$dataString}
            JSON
                ,
                true
            );
            EOT;
    }
}
