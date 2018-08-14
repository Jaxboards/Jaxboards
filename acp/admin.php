<?php

define('INACP', 'true');

require '../inc/classes/mysql.php';
require '../config.php';
require '../inc/classes/jax.php';
require './page.php';

function ss($a)
{
    if (!get_magic_quotes_gpc()) {
        return $a;
    }
    foreach ($a as $k => $v) {
        $a[$k] = is_array($v) ? ss($v) : stripslashes($v);
    }

    return $a;
}

$_GET = ss($_GET);
$_POST = ss($_POST);
$_COOKIE = ss($_COOKIE);

$DB = new MySQL();
$DB->connect($CFG['sql_host'], $CFG['sql_username'], $CFG['sql_password'], $CFG['sql_db'], $CFG['sql_prefix']);

require_once '../domaindefinitions.php';

$JAX = new JAX();
$JAX->getUser($JAX->c['auid'], $JAX->c['apass']);
$PERMS = $JAX->getPerms($JAX->userData['group_id']);
if (!$PERMS['can_access_acp']) {
    header('Location: ./');
    die();
}

$PAGE = new PAGE();
$PAGE->append('username', $JAX->userData['display_name']);
$PAGE->title($PAGE->getCFGSetting('boardname').' - ACP');
$PAGE->addNavMenu('Settings', '?act=settings', array('?act=settings&do=global' => 'Global Settings', '?act=settings&do=shoutbox' => 'Shoutbox', '?act=settings&do=pages' => 'Custom Pages', '?act=settings&do=birthday' => 'Birthdays', '?act=settings&do=domains' => 'Domain Setup'));
$PAGE->addNavMenu('Members', '?act=members', array('?act=members&do=edit' => 'Edit', '?act=members&do=prereg' => 'Pre-Register', '?act=members&do=merge' => 'Account Merge', '?act=members&do=delete' => 'Delete Account', '?act=members&do=massmessage' => 'Mass Message', '?act=members&do=ipbans' => 'IP Bans', '?act=members&do=validation' => 'Validation'));
$PAGE->addNavMenu('Groups', '?act=groups', array('?act=groups&do=perms' => 'Edit Permissions', '?act=groups&do=create' => 'Create Group', '?act=groups&do=delete' => 'Delete Groups'));
$PAGE->addNavMenu('Themes', '?act=themes', array('?act=themes' => 'Manage Skin(s)', '?act=themes&page=create' => 'Create Skin'));
$PAGE->addNavMenu('Posting', '?act=posting', array('?act=posting&do=emoticons' => 'Emoticons', '?act=posting&do=wordfilter' => 'Word Filter', '?act=posting&do=postrating' => 'Post Rating'));
$PAGE->addNavMenu('Forums', '?act=forums', array('?act=forums&do=order' => 'Manage', '?act=forums&do=create' => 'Create Forum', '?act=forums&do=createc' => 'Create Category', '?act=stats' => 'Refresh Statistics'));
$PAGE->addNavMenu('Tools', '?act=tools', array('?act=tools&do=files' => 'File Manager', '?act=tools&do=backup' => 'Backup Forum'));
//$PAGE->addNavMenu("Arcade","?act=arcade",Array("?act=arcade&do=index"=>"Under construction!"));

$a = isset($JAX->g['act']) ? $JAX->g['act'] : null;

if ($a && file_exists("./pages/${a}.php")) {
    require "./pages/${a}.php";
} else {
    require './pages/index.php';
}

$PAGE->out();
