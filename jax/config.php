<?php

declare(strict_types=1);

namespace Jax;

use function array_key_exists;
use function array_merge;
use function defined;
use function file_exists;
use function file_put_contents;
use function json_encode;

use const JSON_PRETTY_PRINT;

/**
 * @psalm-api
 */
final class Config
{
    function __construct(
        private ServiceConfig $serviceConfig,
        private DomainDefinitions $domainDefinitions,
    ){}

    public function get(): array
    {
        return array_merge($this->serviceConfig->get(), $this::getBoardConfig());
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

        $boardPath = $this->domainDefinitions->getBoardPath();
        if (!$boardPath) {
            $boardConfig = ['noboard' => 1];

            return $boardConfig;
        }

        require_once $boardPath . '/config.php';

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
