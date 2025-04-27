<?php

declare(strict_types=1);

namespace Jax;

use function array_merge;
use function file_exists;

final class ServiceConfig
{
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
    public function getServiceConfig(): ?array
    {
        static $serviceConfig = null;

        if ($serviceConfig) {
            return $serviceConfig;
        }

        if (file_exists(JAXBOARDS_ROOT . '/config.php')) {
            require_once JAXBOARDS_ROOT . '/config.php';
            $serviceConfig = $CFG;
        }

        return $serviceConfig;
    }

    public function getSetting(string $key): mixed
    {
        $config = $this->get();

        return $config[$key] ?? null;
    }

    public function override(?array $override = null): array
    {
        static $overrideConfig = [];

        if ($override) {
            $overrideConfig = $override;
        }

        return $overrideConfig;
    }
}
