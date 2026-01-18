<?php

declare(strict_types=1);

namespace Jax;

use function preg_match;
use function str_replace;

/**
 * Figures out what board we're talking about if it's a service,
 * but regardless defines some important paths.
 *
 * @see https://github.com/jaxboards/jaxboards Jaxboards Github Repo
 */
final class DomainDefinitions
{
    private string $boardURL = '';

    private readonly ?string $prefix;

    public function __construct(
        private readonly FileSystem $fileSystem,
        private readonly ServiceConfig $serviceConfig,
        Request $request,
    ) {
        // Figure out url.
        $host =
            $request->server('SERVER_NAME') ??
            (string) $this->serviceConfig->getSetting('domain');
        $port = $request->server('SERVER_PORT') ?? '443';
        $scheme = $request->server('REQUEST_SCHEME') ?? 'https';

        // Build the url.
        $boardURL = $scheme . '://' . $host;
        if (
            !($port === '443' && $scheme === 'https') &&
            !($port === '80' && $scheme === 'http')
        ) {
            $boardURL .= $port !== '' && $port !== '0' ? ':' . $port : '';
        }

        $this->boardURL = $boardURL;

        $this->prefix = $this->getPrefix($host);
    }

    public function isBoardFound(): bool
    {
        return (bool) $this->prefix;
    }

    public function getBoardURL(): string
    {
        return $this->boardURL;
    }

    public function getDefaultThemePath(): string
    {
        return 'Service/Themes/Default/';
    }

    public function getSoundsURL(): string
    {
        return $this->boardURL . '/Sounds';
    }

    public function getServiceThemePath(): string
    {
        return 'Service/Themes';
    }

    public function getBoardPath(): string
    {
        return $this->fileSystem->pathJoin('boards', $this->prefix);
    }

    public function getBoardPathUrl(): string
    {
        return $this->boardURL .
            '/' .
            $this->fileSystem->pathJoin('boards', $this->prefix);
    }

    /**
     * Attempts to retrieve the board's slug/prefix from the hostname
     * when in service mode.
     *
     * Given: "test.jaxboards.com" returns "test"
     */
    private function getPrefix(string $host): ?string
    {
        if ($this->serviceConfig->getSetting('service')) {
            $domainMatch = str_replace(
                '.',
                '\.',
                $this->serviceConfig->getSetting('domain'),
            );

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

        return $this->serviceConfig->getSetting('prefix');
    }
}
