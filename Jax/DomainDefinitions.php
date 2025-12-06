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

    private string $soundsURL = '';

    private string $serviceThemePath = '';

    private string $boardPath = '';

    private string $boardPathURL = '';

    private string $defaultThemePath = '';

    private bool $boardFound = false;

    /**
     * @SuppressWarnings("PHPMD.Superglobals")
     */
    public function __construct(
        private readonly FileSystem $fileSystem,
        private readonly ServiceConfig $serviceConfig,
        Request $request,
    ) {

        // Figure out url.
        $host = $request->server('SERVER_NAME') ?? (string) $this->serviceConfig->getSetting('domain');
        $port = $request->server('SERVER_PORT') ?? '443';
        $scheme = $request->server('REQUEST_SCHEME') ?? 'https';

        // Build the url.
        $boardURL = '//' . $host;
        if (
            !($port === '443' && $scheme === 'https')
            && !($port === '80' && $scheme === 'http')
        ) {
            $boardURL .= ($port !== '' && $port !== '0' ? ':' . $port : '');
        }

        $this->boardURL = $boardURL;
        $this->soundsURL = $this->boardURL . '/Sounds';


        // Get prefix.
        $prefix = $this->serviceConfig->getSetting('prefix');
        if ($this->serviceConfig->getSetting('service')) {
            $domainMatch = str_replace('.', '\.', $this->serviceConfig->getSetting('domain'));

            preg_match('@(.*)\.' . $domainMatch . '@i', $host, $matches);
            if ($matches[1] !== '') {
                $prefix = $matches[1];
                $this->serviceConfig->override([
                    'prefix' => $prefix,
                    'sql_prefix' => $prefix . '_',
                ]);
            } else {
                $prefix = null;
            }
        }

        $this->defaultThemePath = 'Service/Themes/Default/';
        $this->serviceThemePath = 'Service/Themes';

        if (!$prefix) {
            return;
        }

        $this->boardFound = true;
        $this->boardPath = $this->fileSystem->pathJoin('boards', $prefix);
        $this->boardPathURL = $this->boardURL . '/' . $this->fileSystem->pathJoin('boards', $prefix);
    }

    public function isBoardFound(): bool
    {
        return $this->boardFound;
    }

    public function getBoardURL(): string
    {
        return $this->boardURL;
    }

    public function getDefaultThemePath(): string
    {
        return $this->defaultThemePath;
    }

    public function getSoundsURL(): string
    {
        return $this->soundsURL;
    }

    public function getServiceThemePath(): string
    {
        return $this->serviceThemePath;
    }

    public function getBoardPath(): string
    {
        return $this->boardPath;
    }

    public function getBoardPathUrl(): string
    {
        return $this->boardPathURL;
    }
}
