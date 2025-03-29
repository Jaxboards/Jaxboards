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

// Figure out url.
$host = $_SERVER['SERVER_NAME'];
// Build the url.
$baseURL = '//' . ($_SERVER['SERVER_NAME'] ?? $CFG['domain']);
if (
    !($_SERVER['SERVER_PORT'] === '443' && $_SERVER['REQUEST_SCHEME'] === 'https')
    && !($_SERVER['SERVER_PORT'] === '80' && $_SERVER['REQUEST_SCHEME'] === 'http')
) {
    $baseURL .= (isset($_SERVER['SERVER_PORT']) ? ':' . $_SERVER['SERVER_PORT'] : '');
}

define('BOARDURL', $baseURL);

define('SOUNDSURL', BOARDURL . 'Sounds/');
define('SCRIPTURL', BOARDURL . 'Script/');
define('FLAGURL', BOARDURL . 'Service/flags/');

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
    define('BOARDPATH', JAXBOARDS_ROOT . '/boards/' . $prefix . '/');
    define('BOARDPATHURL', BOARDURL . 'boards/' . $prefix . '/');
    define('STHEMEPATH', JAXBOARDS_ROOT . '/Service/Themes/');
    define('AVAURL', BOARDURL . 'Service/Themes/Default/avatars/');
    define('BOARDCONFIG', BOARDPATH . 'config.php');
    if ($DB) {
        $DB->prefix($CFG['sql_prefix']);
    }

    $tempCFG = $CFG;
    if (@include_once BOARDCONFIG) {
        $CFG = array_merge($tempCFG, $CFG);
    } else {
        $CFG['noboard'] = 1;
    }
} else {
    $CFG['noboard'] = 1;
}
