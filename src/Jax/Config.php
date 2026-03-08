<?php

declare(strict_types=1);

namespace Jax;

use function array_key_exists;
use function array_merge;

final class Config
{
    private readonly string $boardConfigPath;

    /**
     * @param null|array<mixed> $boardConfig
     */
    public function __construct(
        private readonly ServiceConfig $serviceConfig,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly FileSystem $fileSystem,
        private ?array $boardConfig = null,
    ) {
        $this->boardConfigPath = $this->fileSystem->pathJoin($this->domainDefinitions->getBoardPath(), '/config.php');
    }

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

        $boardConfig = [];

        if ($this->fileSystem->getFileInfo($this->boardConfigPath)->isFile()) {
            $boardConfig = require_once $this->fileSystem->pathFromRoot($this->boardConfigPath);
        }

        $this->boardConfig = $boardConfig;

        return $boardConfig;
    }

    public function getSetting(string $key): mixed
    {
        $config = $this->get();

        if (array_key_exists($key, $config)) {
            return $config[$key];
        }

        return null;
    }

    public function isBoardFound(): bool
    {
        return $this->getBoardConfig() !== [];
    }

    public function hasInstalled(): bool
    {
        return $this->serviceConfig->hasInstalled();
    }

    /**
     * Write board config.
     *
     * @param array<string,mixed> $data
     */
    public function write(array $data): void
    {
        $this->boardConfig = array_merge($this->boardConfig ?? [], $data);

        $this->fileSystem->putContents(
            $this->domainDefinitions->getBoardPath() . '/config.php',
            $this->serviceConfig->configFileContents($this->boardConfig),
        );
    }
}
