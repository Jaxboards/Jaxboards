<?php

declare(strict_types=1);

namespace Jax;

use Error;

use function is_string;
use function preg_match;
use function str_replace;

/**
 * Helper class to generate paths to files on the filesystem.
 * Knows where to find files for specific board instances in service mode.
 */
final readonly class FilePaths
{
    private string $prefix;

    public function __construct(
        private FileSystem $fileSystem,
        private ServiceConfig $serviceConfig,
        Request $request,
    ) {
        $this->prefix = $this->getPrefix(
            $request->server('SERVER_NAME') ?? (string) $this->serviceConfig->getSetting('domain'),
        );
    }

    public function getDefaultThemePath(): string
    {
        return $this->getServiceThemePath() . '/Default';
    }

    public function getServiceThemePath(): string
    {
        return 'public/Service/Themes';
    }

    /**
     * Returns the path to a public static asset
     */
    public function getStaticAsset(string ...$paths): string
    {
        $assetPath = $this->fileSystem->pathJoin('public/', ...$paths);
        $timestamp = $this->fileSystem->getFileInfo($assetPath)->getMTime();
        return str_replace('public/', '/', $assetPath) . "?{$timestamp}";
    }

    /**
     * Gets a file path for a specific board instance
     */
    public function getBoardPath(string ...$paths): string
    {
        return $this->fileSystem->pathJoin('public/boards', $this->prefix, ...$paths);
    }

    /**
     * Returns a static asset for a specific board instance
     */
    public function getBoardStaticAsset(string ...$paths): string
    {
        return str_replace('public/', '/', $this->getBoardPath(...$paths));
    }

    /**
     * Attempts to retrieve the board's slug/prefix from the hostname
     * when in service mode.
     *
     * Given: "test.example.com" returns "test"
     */
    private function getPrefix(string $host): string
    {
        if ($this->serviceConfig->getSetting('service')) {
            $domainMatch = str_replace('.', '\.', $this->serviceConfig->getSetting('domain'));

            preg_match("/(.*)\\.{$domainMatch}/i", $host, $matches);

            if ($matches && $matches[1] !== '') {
                $prefix = $matches[1];
                $this->serviceConfig->override([
                    'prefix' => $prefix,
                    'sql_prefix' => $prefix . '_',
                ]);

                return $prefix;
            }
        }

        $prefix = $this->serviceConfig->getSetting('prefix');

        if (!is_string($prefix)) {
            throw new Error('Unable to determine board prefix');
        }

        return $prefix;
    }
}
