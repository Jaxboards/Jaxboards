<?php

/**
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

// This file must be required after mysql connecting.
if (!isset($DB)) {
    exit('This file must be required after mysql connecting');
}

function pathjoin(...$paths): ?string
{
    return preg_replace('@\/+@', '/', implode('/', $paths) . '/');
}

// Figure out url.
$host = $_SERVER['SERVER_NAME'];
// Build the url.
$boardURL = '//' . ($_SERVER['SERVER_NAME'] ?? $CFG['domain']);
if (
    !($_SERVER['SERVER_PORT'] === '443' && $_SERVER['REQUEST_SCHEME'] === 'https')
    && !($_SERVER['SERVER_PORT'] === '80' && $_SERVER['REQUEST_SCHEME'] === 'http')
) {
    $boardURL .= (isset($_SERVER['SERVER_PORT']) ? ':' . $_SERVER['SERVER_PORT'] : '');
}

define('BOARDURL', $boardURL . '/');
define('SOUNDSURL', pathjoin(BOARDURL, 'Sounds'));

$domain_match = str_replace('.', '\.', $CFG['domain']);
// Get prefix.
if ($CFG['service']) {
    preg_match('@(.*)\.' . $domain_match . '@i', (string) $host, $matches);
    if (isset($matches[1]) && $matches[1]) {
        $prefix = $matches[1];
        $CFG['prefix'] = $prefix;
        $CFG['sql_prefix'] = $prefix . '_';
    } else {
        $prefix = '';
    }
} else {
    $prefix = $CFG['prefix'];
}

if ($prefix) {
    define('BOARDPATH', pathjoin(JAXBOARDS_ROOT, 'boards', $prefix));
    define('BOARDPATHURL', pathjoin(BOARDURL, 'boards', $prefix));
    define('STHEMEPATH', pathjoin(JAXBOARDS_ROOT, 'Service/Themes'));
    define('AVAURL', pathjoin(BOARDURL, 'Service/Themes/Default/avatars'));
    if ($DB) {
        $DB->prefix($CFG['sql_prefix']);
    }

    $tempCFG = $CFG;
    if (@include_once BOARDPATH . 'config.php') {
        $CFG = array_merge($tempCFG, $CFG);
    } else {
        $CFG['noboard'] = 1;
    }
} else {
    $CFG['noboard'] = 1;
}
