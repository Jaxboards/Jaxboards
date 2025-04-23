<?php

declare(strict_types=1);

use ACP\Page;
use Jax\Config;
use Jax\Jax;
use Jax\MySQL;

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
require_once JAXBOARDS_ROOT . '/jax/autoload.php';

require_once JAXBOARDS_ROOT . '/domaindefinitions.php';

$CFG = Config::get();

$DB = new MySQL();
$DB->connect(
    $CFG['sql_host'],
    $CFG['sql_username'],
    $CFG['sql_password'],
    $CFG['sql_db'],
    $CFG['sql_prefix'],
);

$JAX = new JAX();
if (isset($_SESSION['auid'])) {
    $userData = $DB->getUser($_SESSION['auid']);
    $PERMS = $DB->getPerms($userData['group_id']);
} else {
    $PERMS = [
        'can_access_acp' => false,
    ];
}

if (!$PERMS['can_access_acp']) {
    header('Location: ./');

    exit;
}

$USER = $DB->getUser();
$PAGE = new Page();
$PAGE->append('username', $USER['display_name']);
$PAGE->title(Config::getSetting('boardname') . ' - ACP');
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

if ($act && file_exists("./page/{$act}.php")) {
    $acpPageClass = "ACP\\Page\\{$act}";
    $page = new $acpPageClass();
    $page->route();
}

$PAGE->out();
