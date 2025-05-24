<?php

declare(strict_types=1);

namespace Jax;

use function array_key_exists;
use function array_merge;
use function file_put_contents;

final class Config
{
    /**
     * @var null|array<string, mixed>
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
    public function getBoardConfig(): array
    {
        if ($this->boardConfig) {
            return $this->boardConfig;
        }

        $CFG = [];

        require_once $this->domainDefinitions->getBoardPath() . '/config.php';

        return $this->boardConfig = $CFG;
    }

    public function getSetting(string $key): mixed
    {
        $config = $this->get();

        if (array_key_exists($key, $config)) {
            return $config[$key];
        }

        return null;
    }

    /**
     * Write board config.
     *
     * @param array<string,mixed> $data
     */
    public function write(array $data): void
    {
        $this->boardConfig = array_merge($this->boardConfig ?? [], $data);

        file_put_contents($this->domainDefinitions->getBoardPath() . '/config.php', $this->serviceConfig->configFileContents($this->boardConfig));
    }
}
