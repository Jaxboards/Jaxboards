<?php

declare(strict_types=1);

namespace Jax;

use function array_merge;
use function dirname;
use function file_exists;
use function file_put_contents;
use function json_encode;

use const JSON_PRETTY_PRINT;

final class ServiceConfig
{
    private bool $installed = false;

    public function __construct()
    {
        // Prefetch service config
        $this->get();
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
        static $serviceConfig = null;

        if ($serviceConfig) {
            return $serviceConfig;
        }

        $serviceConfig = [];
        $configPath = dirname(__DIR__) . '/config.php';
        $serviceConfigPath = dirname(__DIR__) . '/config.default.php';
        $this->installed = file_exists($configPath);

        require_once $this->installed ? $configPath : $serviceConfigPath;

        if (isset($CFG)) {
            return $serviceConfig = (array) $CFG;
        }

        return $serviceConfig;
    }

    public function hasInstalled(): bool
    {
        return $this->installed;
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
        static $overrideConfig = [];

        if ($override) {
            $overrideConfig = $override;
        }

        return $overrideConfig;
    }

    /**
     * Write service config during installation.
     *
     * @param array<string,mixed> $data
     */
    public function writeServiceConfig(array $data): void
    {
        file_put_contents(dirname(__DIR__) . '/config.php', $this->configFileContents($data));
    }

    public function configFileContents(array $data): string
    {
        $dataString = json_encode($data, JSON_PRETTY_PRINT);

        return <<<EOT
            <?php
            \$CFG = json_decode(
            <<<'JSON'
            {$dataString}
            JSON
                ,
                true
            );
            EOT;
    }
}
