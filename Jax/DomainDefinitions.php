<?php

declare(strict_types=1);

namespace Jax;

use function dirname;
use function implode;
use function preg_match;
use function preg_replace;
use function str_replace;

/**
 * Figures out what board we're talking about if it's a service,
 * but regardless defines some important paths.
 *
 * PHP Version 5.4.0
 *
 * @see https://github.com/jaxboards/jaxboards Jaxboards Github Repo
 */
function pathjoin(string ...$paths): ?string
{
    return preg_replace('@\/+@', '/', implode('/', $paths));
}

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
    public function __construct(private readonly ServiceConfig $serviceConfig)
    {

        // Figure out url.
        $host = $_SERVER['SERVER_NAME'] ?? $this->serviceConfig->getSetting('domain');
        $port = $_SERVER['SERVER_PORT'] ?? '443';
        $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'https';

        // Build the url.
        $boardURL = '//' . $host;
        if (
            !($port === '443' && $scheme === 'https')
            && !($port === '80' && $scheme === 'http')
        ) {
            $boardURL .= ($port ? ':' . $port : '');
        }

        $this->boardURL = $boardURL;
        $this->soundsURL = $this->boardURL . '/Sounds';


        // Get prefix.
        $prefix = $this->serviceConfig->getSetting('prefix');
        if ($this->serviceConfig->getSetting('service')) {
            $domainMatch = str_replace('.', '\.', $this->serviceConfig->getSetting('domain'));

            preg_match('@(.*)\.' . $domainMatch . '@i', (string) $host, $matches);
            if (isset($matches[1]) && $matches[1]) {
                $prefix = $matches[1];
                $this->serviceConfig->override([
                    'prefix' => $prefix,
                    'sql_prefix' => $prefix . '_',
                ]);
            } else {
                $prefix = null;
            }
        }

        $this->defaultThemePath = pathjoin(dirname(__DIR__), $this->serviceConfig->getSetting('dthemepath'));
        $this->serviceThemePath = pathjoin(dirname(__DIR__), 'Service/Themes');

        if (!$prefix) {
            return;
        }

        $this->boardFound = true;
        $this->boardPath = pathjoin(dirname(__DIR__), 'boards', $prefix);
        $this->boardPathURL = $this->boardURL . '/' . pathjoin('boards', $prefix);
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
