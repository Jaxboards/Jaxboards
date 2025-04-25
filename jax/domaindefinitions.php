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

final readonly class DomainDefinitions
{
    public function __construct(private Config $config) {}

    public function defineConstants(): void
    {

        $serviceConfig = $this->config->getServiceConfig();

        function pathjoin(string ...$paths): ?string
        {
            return preg_replace('@\/+@', '/', implode('/', $paths) . '/');
        }

        // Figure out url.
        $host = $_SERVER['SERVER_NAME'];
        // Build the url.
        $boardURL = '//' . ($_SERVER['SERVER_NAME'] ?? $serviceConfig['domain']);
        if (
            !($_SERVER['SERVER_PORT'] === '443' && $_SERVER['REQUEST_SCHEME'] === 'https')
            && !($_SERVER['SERVER_PORT'] === '80' && $_SERVER['REQUEST_SCHEME'] === 'http')
        ) {
            $boardURL .= (isset($_SERVER['SERVER_PORT']) ? ':' . $_SERVER['SERVER_PORT'] : '');
        }

        // phpcs:enable

        define('BOARDURL', $boardURL . '/');
        define('SOUNDSURL', pathjoin(BOARDURL, 'Sounds'));

        $domainMatch = str_replace('.', '\.', $serviceConfig['domain']);

        $prefix = $serviceConfig['prefix'];

        // Get prefix.
        if ($serviceConfig['service']) {
            preg_match('@(.*)\.' . $domainMatch . '@i', (string) $host, $matches);
            if (isset($matches[1]) && $matches[1]) {
                $prefix = $matches[1];
                $this->config->override([
                    'prefix' => $prefix,
                    'sql_prefix' => $prefix . '_',
                ]);
            } else {
                $prefix = null;
            }
        }

        define('STHEMEPATH', pathjoin(JAXBOARDS_ROOT, 'Service/Themes'));

        if (!$prefix) {
            return;
        }

        define('BOARDPATH', pathjoin(JAXBOARDS_ROOT, 'boards', $prefix));
        define('BOARDPATHURL', BOARDURL . pathjoin('boards', $prefix));
    }
}
