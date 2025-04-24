<?php

declare(strict_types=1);

use ACP\Page;
use DI\Container;
use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\User;

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
$container = new Container();

require_once JAXBOARDS_ROOT . '/domaindefinitions.php';

$CFG = $container->get(Config::class)->get();

$DB = $container->get(Database::class);
$DB->connect(
    $CFG['sql_host'],
    $CFG['sql_username'],
    $CFG['sql_password'],
    $CFG['sql_db'],
    $CFG['sql_prefix'],
);
$USER = $container->get(User::class);

$JAX = $container->get(Jax::class);

if (isset($_SESSION['auid'])) {
    $userData = $USER->getUser($_SESSION['auid']);
}

if (!$USER->getPerm('can_access_acp')) {
    header('Location: ./');

    exit;
}

$PAGE = $container->get(Page::class);
$PAGE->append('username', $USER->get('display_name'));
$PAGE->title($CFG['boardname'] . ' - ACP');
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
    $page = $container->get('ACP\Page\\' . $act);
    $page->route();
}

$PAGE->out();
