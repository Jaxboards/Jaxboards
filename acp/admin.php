<?php
/**
 * Admin control panel.
 *
 * PHP Version 5.3.7
 *
 * @category Jaxboards
 * @package  Jaxboards
 *
 * @author  Sean Johnson <seanjohnson08@gmail.com>
 * @author  World's Tallest Ladder <wtl420@users.noreply.github.com>
 * @license MIT <https://opensource.org/licenses/MIT>
 *
 * @link https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
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

define('INACP', 'true');

require JAXBOARDS_ROOT . '/config.php';
require JAXBOARDS_ROOT . '/inc/classes/jax.php';
require JAXBOARDS_ROOT . '/inc/classes/mysql.php';
require JAXBOARDS_ROOT . '/acp/page.php';

/**
 * Strip slashes from input, recurisvely.
 *
 * @param mixed $input The input to strip slashes from
 *
 * @return mixed The input, without slashes
 */
function recursiveStripSlashes($input)
{
    if (!get_magic_quotes_gpc()) {
        return $input;
    }
    foreach ($input as $key => $value) {
        $input[$key] = is_array($value) ?
            recursiveStripSlashes($value) : stripslashes($value);
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
    $CFG['sql_prefix']
);

require_once '../domaindefinitions.php';

$JAX = new JAX();
if (isset($_SESSION['auid'])) {
    $JAX->getUser($_SESSION['auid']);
    $PERMS = $JAX->getPerms($JAX->userData['group_id']);
} else {
    $PERMS = array(
        'can_access_acp' => false,
    );
}
if (!$PERMS['can_access_acp']) {
    header('Location: ./');
    die();
}

$PAGE = new PAGE();
$PAGE->append('username', $JAX->userData['display_name']);
$PAGE->title($PAGE->getCFGSetting('boardname') . ' - ACP');
$PAGE->addNavMenu(
    'Settings',
    '?act=settings',
    array(
        '?act=settings&do=global' => 'Global Settings',
        '?act=settings&do=shoutbox' => 'Shoutbox',
        '?act=settings&do=pages' => 'Custom Pages',
        '?act=settings&do=birthday' => 'Birthdays',
    )
);
$PAGE->addNavMenu(
    'Members',
    '?act=members',
    array(
        '?act=members&do=edit' => 'Edit',
        '?act=members&do=prereg' => 'Pre-Register',
        '?act=members&do=merge' => 'Account Merge',
        '?act=members&do=delete' => 'Delete Account',
        '?act=members&do=massmessage' => 'Mass Message',
        '?act=members&do=ipbans' => 'IP Bans',
        '?act=members&do=validation' => 'Validation',
    )
);
$PAGE->addNavMenu(
    'Groups',
    '?act=groups',
    array(
        '?act=groups&do=perms' => 'Edit Permissions',
        '?act=groups&do=create' => 'Create Group',
        '?act=groups&do=delete' => 'Delete Groups',
    )
);
$PAGE->addNavMenu(
    'Themes',
    '?act=themes',
    array(
        '?act=themes' => 'Manage Skin(s)',
        '?act=themes&do=create' => 'Create Skin',
    )
);
$PAGE->addNavMenu(
    'Posting',
    '?act=posting',
    array(
        '?act=posting&do=emoticons' => 'Emoticons',
        '?act=posting&do=wordfilter' => 'Word Filter',
        '?act=posting&do=postrating' => 'Post Rating',
    )
);
$PAGE->addNavMenu(
    'Forums',
    '?act=forums',
    array(
        '?act=forums&do=order' => 'Manage',
        '?act=forums&do=create' => 'Create Forum',
        '?act=forums&do=createc' => 'Create Category',
        '?act=stats' => 'Refresh Statistics',
    )
);
$PAGE->addNavMenu(
    'Tools',
    '?act=tools',
    array(
        '?act=tools&do=files' => 'File Manager',
        '?act=tools&do=backup' => 'Backup Forum',
    )
);

$a = isset($JAX->g['act']) ? $JAX->g['act'] : null;

if ($a && file_exists("./pages/${a}.php")) {
    include_once "./pages/${a}.php";
}
$PAGE->out();
