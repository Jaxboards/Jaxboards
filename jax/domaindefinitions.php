<?php

declare(strict_types=1);

namespace Jax;

use function define;
use function defined;
use function implode;
use function preg_match;
use function preg_replace;
use function str_replace;

/*
 * Figures out what board we're talking about if it's a service,
 * but regardless defines some important paths.
 *
 * PHP Version 5.4.0
 *
 * @see https://github.com/jaxboards/jaxboards Jaxboards Github Repo
 */
if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', __DIR__);
}

function pathjoin(string ...$paths): ?string
{
    return preg_replace('@\/+@', '/', implode('/', $paths) . '/');
}

final class DomainDefinitions
{

    private string $boardURL = '';

    private string $soundsURL = '';

    private string $serviceThemePath = '';

    private string $boardPath = '';

    private string $boardPathURL = '';

    public function __construct(private readonly ServiceConfig $serviceConfig)
    {
        $serviceConfig = $this->serviceConfig->get();


        // Figure out url.
        $host = $_SERVER['SERVER_NAME'] ?? $serviceConfig['domain'];
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

        $this->boardURL = $boardURL . '/';
        $this->soundsURL = pathjoin($this->boardURL, 'Sounds');

        $domainMatch = str_replace('.', '\.', $serviceConfig['domain']);

        // Get prefix.
        $prefix = $serviceConfig['prefix'];
        if ($serviceConfig['service']) {
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

        $this->serviceThemePath = pathjoin(JAXBOARDS_ROOT, 'Service/Themes');

        if (!$prefix) {
            return;
        }

        $this->boardPath = pathjoin(JAXBOARDS_ROOT, 'boards', $prefix);
        $this->boardPathURL = $this->boardURL . pathjoin('boards', $prefix);
    }

    public function getBoardURL(): string
    {
        return $this->boardURL;
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
