<?php

declare(strict_types=1);

/*
 * Admin control panel.
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
session_start();

if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', dirname(__DIR__));
}

// Load composer dependencies.
require_once JAXBOARDS_ROOT . '/vendor/autoload.php';

require_once JAXBOARDS_ROOT . '/config.php';

require_once JAXBOARDS_ROOT . '/inc/classes/jax.php';

require_once JAXBOARDS_ROOT . '/inc/classes/mysql.php';

require_once JAXBOARDS_ROOT . '/acp/page.php';

/**
 * Strip slashes from input, recursively.
 *
 * @param mixed $input The input to strip slashes from
 *
 * @return mixed The input, without slashes
 */
function recursiveStripSlashes($input): mixed
{
    /*
     *
        if (!get_magic_quotes_gpc()) {
        return $input;
        }
     */

    foreach ($input as $key => $value) {
        $input[$key] = is_array($value)
            ? recursiveStripSlashes($value) : stripslashes((string) $value);
    }

    return $input;
}

$_GET = recursiveStripSlashes($_GET);
$_POST = recursiveStripSlashes($_POST);
$_COOKIE = recursiveStripSlashes($_COOKIE);

$DB = new MySQL();
$DB->connect(
    $CFG['sql_host'],
    $CFG['sql_username'],
    $CFG['sql_password'],
    $CFG['sql_db'],
    $CFG['sql_prefix'],
);

require_once __DIR__ . '/../domaindefinitions.php';

$JAX = new JAX();
if (isset($_SESSION['auid'])) {
    $JAX->getUser($_SESSION['auid']);
    $PERMS = $JAX->getPerms($JAX->userData['group_id']);
} else {
    $PERMS = [
        'can_access_acp' => false,
    ];
}

if (!$PERMS['can_access_acp']) {
    header('Location: ./');

    exit;
}

$PAGE = new PAGE();
$PAGE->append('username', $JAX->userData['display_name']);
$PAGE->title($PAGE->getCFGSetting('boardname') . ' - ACP');
$PAGE->addNavMenu(
    'Settings',
    '?act=settings',
    [
        '?act=settings&do=birthday' => 'Birthdays',
        '?act=settings&do=global' => 'Global Settings',
        '?act=settings&do=pages' => 'Custom Pages',
        '?act=settings&do=shoutbox' => 'Shoutbox',
    ],
);
$PAGE->addNavMenu(
    'Members',
    '?act=members',
    [
        '?act=members&do=delete' => 'Delete Account',
        '?act=members&do=edit' => 'Edit',
        '?act=members&do=ipbans' => 'IP Bans',
        '?act=members&do=massmessage' => 'Mass Message',
        '?act=members&do=merge' => 'Account Merge',
        '?act=members&do=prereg' => 'Pre-Register',
        '?act=members&do=validation' => 'Validation',
    ],
);
$PAGE->addNavMenu(
    'Groups',
    '?act=groups',
    [
        '?act=groups&do=create' => 'Create Group',
        '?act=groups&do=delete' => 'Delete Groups',
        '?act=groups&do=perms' => 'Edit Permissions',
    ],
);
$PAGE->addNavMenu(
    'Themes',
    '?act=themes',
    [
        '?act=themes&do=create' => 'Create Skin',
        '?act=themes' => 'Manage Skin(s)',
    ],
);
$PAGE->addNavMenu(
    'Posting',
    '?act=posting',
    [
        '?act=posting&do=emoticons' => 'Emoticons',
        '?act=posting&do=postrating' => 'Post Rating',
        '?act=posting&do=wordfilter' => 'Word Filter',
    ],
);
$PAGE->addNavMenu(
    'Forums',
    '?act=forums',
    [
        '?act=forums&do=create' => 'Create Forum',
        '?act=forums&do=createc' => 'Create Category',
        '?act=forums&do=order' => 'Manage',
        '?act=forums&do=recountstats' => 'Recount Statistics',
    ],
);
$PAGE->addNavMenu(
    'Tools',
    '?act=tools',
    [
        '?act=tools&do=backup' => 'Backup Forum',
        '?act=tools&do=files' => 'File Manager',
        '?act=tools&do=errorlog' => 'View Error Log',
    ],
);

$act = $JAX->g['act'] ?? null;

if ($act && file_exists("./pages/{$act}.php")) {
    require_once "./pages/{$act}.php";

    $page = new $act();
    $page->route();
}

$PAGE->out();
