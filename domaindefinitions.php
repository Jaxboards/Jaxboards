<?php
/**
 * Figures out what board we're talking about if it's a service,
 * but regardless defines some important paths
 *
 * PHP Version 5.4.0
 *
 * @category Jaxboards
 * @package  Jaxboards
 * @author   Sean Johnson <seanjohnson08@gmail.com>
 * @author   World's Tallest Ladder <wtl420@users.noreply.github.com>
 * @license  MIT <https://opensource.org/licenses/MIT>
 * @link     https://github.com/jaxboards/jaxboards Jaxboards Github Repo
 */

if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', __DIR__);
}

//this file must be required after mysql connecting
if (!isset($DB)) {
    die('This file must be required after mysql connecting');
}

// figure out url
$host = $_SERVER['SERVER_NAME'];
// build the url
$baseURL = (isset($_SERVER['REQUEST_SCHEME'])?
    $_SERVER['REQUEST_SCHEME']:'https').'://';
$baseURL .= (isset($_SERVER['SERVER_NAME'])?
    $_SERVER['SERVER_NAME']:$CFG['domain']);
if (!($_SERVER['SERVER_PORT'] === '443' && $_SERVER['REQUEST_SCHEME'] === 'https')
    && !($_SERVER['SERVER_PORT'] === '80' && $_SERVER['REQUEST_SCHEME'] === 'http')
) {
    $baseURL .= (isset($_SERVER['SERVER_PORT'])?':'.$_SERVER['SERVER_PORT']:'');
}
define('BOARDURL', $baseURL.'/');

define('SOUNDSURL', BOARDURL.'Sounds/');
define('SCRIPTURL', BOARDURL.'Script/');
define('FLAGURL', BOARDURL.'flags/');

$domain_match = str_replace('.', '\\.', $CFG['domain']);
// get prefix
if ($CFG['service']) {
    preg_match('@(.*)\\.'.$domain_match.'@i', $host, $matches);
    if (isset($matches[1]) && $matches[1]) {
        $prefix = $matches[1];
    } else {
        $prefix = '';
    }

    // Check for custom domain
    if (!$prefix) {
        $result = $DB->safespecial(
            'SELECT prefix FROM `domains` WHERE domain=?',
            [],
            $DB->basicvalue($host)
        );
        $prefix = $DB->row($result);
        $DB->disposeresult($result);
        if ($prefix) {
            $prefix = $prefix['prefix'];
            $CFG['prefix'] = $prefix;
        }
    }
} else {
    $prefix = $CFG['prefix'];
}

if ($prefix) {
    define('BOARDPATH', JAXBOARDS_ROOT.'/boards/'.$prefix.'/');
    define('BOARDPATHURL', BOARDURL.'boards/'.$prefix.'/');
    define('STHEMEPATH', JAXBOARDS_ROOT.'/Service/Themes/');
    define('AVAURL', BOARDURL.'Service/Themes/Default/avatars/');
    define('BOARDCONFIG', BOARDPATH.'config.php');
    if ($DB) {
        $DB->prefix($prefix.'_');
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

