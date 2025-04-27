<?php

declare(strict_types=1);

namespace Jax;

use function array_merge;
use function file_exists;

final class ServiceConfig
{
    private $installed = false;

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
        if (file_exists(dirname(__DIR__) . '/config.php')) {
            $this->installed = true;
            require_once dirname(__DIR__) . '/config.php';

            if (isset($CFG)) {
                $serviceConfig = (array) $CFG;
            }
        } else {
            // Likely installing, fetch default config
            require_once dirname(__DIR__) . '/config.default.php';

            if (isset($CFG)) {
                $serviceConfig = (array) $CFG;
            }
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
     * @param array<string,mixed>
     */
    public function writeServiceConfig(array $data): void
    {
        file_put_contents(dirname(__DIR__) . '/config.php', $this->configFileContents($data));
    }

    /**
     * @param array<string,mixed>
     */
    public function configFileContents(array $data): string
    {
        $dataString = json_encode($data, JSON_PRETTY_PRINT);

        return <<<EOT
            <?php
            \$CFG = json_decode(
            <<<'EOD'
            {$dataString}
            EOD
                ,
                true
            );
            EOT;
    }
}
