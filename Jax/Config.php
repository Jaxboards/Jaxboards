<?php

declare(strict_types=1);

namespace Jax;

use function array_key_exists;
use function array_merge;
use function file_put_contents;
use function json_encode;

use const JSON_PRETTY_PRINT;

final class Config
{
    /**
     * @var null|array<string,mixed>
     */
    private ?array $boardConfig = null;

    public function __construct(
        private readonly ServiceConfig $serviceConfig,
        private readonly DomainDefinitions $domainDefinitions,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return array_merge($this->serviceConfig->get(), $this->getBoardConfig());
    }

    /**
     * @return array<string,mixed>
     */
    public function getBoardConfig()
    {
        if ($this->boardConfig) {
            return $this->boardConfig;
        }

        require_once $this->domainDefinitions->getBoardPath() . '/config.php';

        return $this->boardConfig = (array) $CFG;
    }

    public function getSetting(string $key): mixed
    {
        $config = $this->get();

        if (array_key_exists($key, $config)) {
            return $config[$key];
        }

        return null;
    }

    public function write($data): void
    {
        $this->boardConfig = $data;

        file_put_contents($this->domainDefinitions->getBoardPath() . '/config.php', $this->configFileContents($data));
    }

    // Only used during installation
    public function writeServiceConfig($data): void
    {
        file_put_contents(JAXBOARDS_ROOT . '/config.php', $this->configFileContents($data));
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
