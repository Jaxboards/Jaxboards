<?php

declare(strict_types=1);

namespace Jax;

use function array_key_exists;
use function array_merge;
use function defined;
use function file_put_contents;
use function json_encode;

use const JSON_PRETTY_PRINT;

/**
 * @psalm-api
 */
final class Config
{
    public function get(): array
    {
        return array_merge(self::getServiceConfig(), self::getBoardConfig(), self::override());
    }

    public function getServiceConfig()
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

    public function getBoardConfig($write = null)
    {
        static $boardConfig = null;

        if ($write) {
            $boardConfig = array_merge($boardConfig, $write);
        }

        if ($boardConfig) {
            return $boardConfig;
        }

        if (!defined('BOARDPATH')) {
            $boardConfig = ['noboard' => 1];

            return $boardConfig;
        }

        require_once BOARDPATH . '/config.php';

        return $boardConfig = $CFG;
    }

    public function getSetting(string $key)
    {
        $config = self::get();

        if (array_key_exists($key, $config)) {
            return $config[$key];
        }

        return null;
    }

    public function override($override = null)
    {
        static $overrideConfig = [];

        if ($override) {
            $overrideConfig = $override;
        }

        return $overrideConfig;
    }

    public function write($data): void
    {
        $boardConfig = self::getBoardConfig($data);

        if (!defined('BOARDPATH')) {
            throw new Exception('Board config file not determinable');
        }

        file_put_contents(BOARDPATH . 'config.php', self::configFileContents($boardConfig));
    }

    // Only used during installation
    public function writeServiceConfig($data): void
    {
        file_put_contents(JAXBOARDS_ROOT . '/config.php', self::configFileContents($data));
    }

    private function configFileContents($data): string
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
