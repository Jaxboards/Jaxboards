<?php

declare(strict_types=1);

use DI\Container;
use Jax\Config;
use Jax\IPAddress;
use Jax\Jax;
use Jax\MySQL;
use Jax\Page;
use Jax\Sess;

if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', __DIR__);
}

// Load composer dependencies.
require_once JAXBOARDS_ROOT . '/jax/autoload.php';
$container = new Container();

require_once JAXBOARDS_ROOT . '/domaindefinitions.php';

/*
 * Jaxboards. THE ULTIMATE 4UMS WOOOOOOO
 * By Sean John's son (2007 @ 4 AM).
 *
 * PHP Version 8
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */

if ($_GET['showerrors'] ?? false) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// phpcs:enable

header('Cache-Control: no-cache, must-revalidate');

$microtime = microtime(true);

$onLocalHost = in_array($container->get(IPAddress::class)->asHumanReadable(), ['127.0.0.1', '::1'], true);

$CFG = $container->get(Config::class)->get();

// DB connect!
$DB = $container->get(MySQL::class);
if ($onLocalHost) {
    $DB->debugMode = true;
}

$connected = $DB->connect(
    $CFG['sql_host'],
    $CFG['sql_username'],
    $CFG['sql_password'],
    $CFG['sql_db'],
    $CFG['sql_prefix'],
);
if (!$connected) {
    echo 'Could not connect';

    exit(1);
}

// Start a session.
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
session_start();

// Board Service Stuff, get the board as specified by URL.
// Initialize them.
if (isset($CFG['noboard']) && $CFG['noboard']) {
    echo 'board not found';

    exit(1);
}

$PAGE = $container->get(Page::class);
$JAX = $container->get(Jax::class);
$SESS = $container->get(Sess::class);

if (!isset($_SESSION['uid']) && isset($JAX->c['utoken'])) {
    $result = $DB->safeselect(
        ['uid'],
        'tokens',
        'WHERE `token`=?',
        $JAX->c['utoken'],
    );
    $token = $DB->arow($result);
    if ($token) {
        $_SESSION['uid'] = $token['uid'];
    }
}

if (!$SESS->is_bot && isset($_SESSION['uid']) && $_SESSION['uid']) {
    $DB->getUser($_SESSION['uid']);
}

$USER = $DB->getUser();
$PERMS = $DB->getPerms();

// Fix ip if necessary.
if ($USER && $SESS->ip && $SESS->ip !== $USER['ip']) {
    $DB->safeupdate(
        'members',
        [
            'ip' => $container->get(IPAddress::class)->asBinary(),
        ],
        'WHERE id=?',
        $USER['id'],
    );
}

// Load the theme.
$PAGE->loadskin(
    $JAX->pick(
        $SESS->vars['skin_id'] ?? false,
        $USER['skin_id'] ?? false,
    ),
);
$PAGE->loadmeta('global');

// Skin selector.
if (isset($JAX->b['skin_id'])) {
    if (!$JAX->b['skin_id']) {
        $SESS->delvar('skin_id');
        $PAGE->JS('reload');
    } else {
        $SESS->addvar('skin_id', $JAX->b['skin_id']);
        if ($PAGE->jsaccess) {
            $PAGE->JS('reload');
        }
    }
}

if (isset($SESS->vars['skin_id']) && $SESS->vars['skin_id']) {
    $PAGE->append(
        'NAVIGATION',
        '<div class="success" '
        . 'style="position:fixed;bottom:0;left:0;width:100%;">'
        . 'Skin UCP setting being overriden. '
        . '<a href="?skin_id=0">Revert</a></div>',
    );
}

// "Login"
// If they're logged in through cookies, (username & password)
// but the session variable has changed/been removed/not updated for some reason
// this fixes it.
if ($USER && !$SESS->is_bot && $USER['id'] !== $SESS->uid) {
    $SESS->clean($USER['id']);
    $SESS->uid = $USER['id'];
    $SESS->applychanges();
}

// If the user's navigated to a new page, change their action time.
if ($PAGE->jsnewlocation || !$PAGE->jsaccess) {
    $SESS->act($JAX->b['act'] ?? null);
}

// Set Navigation.
$PAGE->path([$JAX->pick($CFG['boardname'], 'Home') => '?']);
$PAGE->append(
    'TITLE',
    $JAX->pick(
        $PAGE->meta('title'),
        $CFG['boardname'],
        'JaxBoards',
    ),
);

if (!$PAGE->jsaccess) {
    if ($USER) {
        $PAGE->append(
            'SCRIPT',
            '<script>const globalsettings='
            . json_encode([
                'sound_im' => $USER['sound_im'] ? 1 : 0,
                'wysiwyg' => $USER['wysiwyg'] ? 1 : 0,
                'can_im' => $PERMS['can_im'] ? 1 : 0,
                'groupid' => $USER['group_id'] ?? 3,
                'username' => $USER['display_name'],
                'userid' => $USER['id'] ?? 0,
            ])
            . '</script>',
        );
    }

    $PAGE->append(
        'SCRIPT',
        '<script src="' . BOARDURL . 'dist/app.js" defer></script>',
    );

    if ($USER && ($PERMS['can_moderate'] || $USER['mod'])) {
        $PAGE->append(
            'SCRIPT',
            '<script type="text/javascript" src="?act=modcontrols&do=load" defer></script>',
        );
    }

    $PAGE->append(
        'CSS',
        '<link rel="stylesheet" type="text/css" href="' . THEMEPATHURL . 'css.css">'
        . '<link rel="preload" as="style" type="text/css" href="./Service/wysiwyg.css" onload="this.onload=null;this.rel=\'stylesheet\'" />',
    );
    if ($PAGE->meta('favicon')) {
        $PAGE->append(
            'CSS',
            '<link rel="icon" href="' . $PAGE->meta('favicon') . '">',
        );
    }

    $PAGE->append(
        'LOGO',
        $PAGE->meta(
            'logo',
            $JAX->pick(
                $CFG['logourl'] ?? false,
                BOARDURL . 'Service/Themes/Default/img/logo.png',
            ),
        ),
    );
    $PAGE->append(
        'NAVIGATION',
        $PAGE->meta(
            'navigation',
            $PERMS['can_moderate']
            ? '<li><a href="?act=modcontrols&do=cp">Mod CP</a></li>' : '',
            $PERMS['can_access_acp']
            ? '<li><a href="./acp/" target="_BLANK">ACP</a></li>' : '',
            isset($CFG['navlinks']) && $CFG['navlinks'] ? $CFG['navlinks'] : '',
        ),
    );
    if ($USER && $USER['id']) {
        $result = $DB->safeselect(
            'COUNT(`id`)',
            'messages',
            'WHERE `read`=0 AND `to`=?',
            $USER['id'],
        );
        $thisrow = $DB->arow($result);
        $nummessages = 0;
        if (is_array($thisrow)) {
            $nummessages = array_pop($thisrow);
        }

        $DB->disposeresult($result);
    }

    if (!isset($nummessages)) {
        $nummessages = 0;
    }

    $PAGE->addvar('inbox', $nummessages);
    if ($nummessages) {
        $PAGE->append(
            'FOOTER',
            '<a href="?act=ucp&what=inbox"><div id="notification" class="newmessage" '
            . 'onclick="this.style.display=\'none\'">You have ' . $nummessages
            . ' new message' . ($nummessages === 1 ? '' : 's') . '</div></a>',
        );
    }

    $version = json_decode(
        file_get_contents(__DIR__ . '/composer.json'),
        null,
        512,
        JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
    )['version'] ?? 'Unknown';

    $PAGE->append(
        'FOOTER',
        '<div class="footer">'
        . "<a href=\"https://jaxboards.github.io\">Jaxboards</a> {$version}! "
        // Removed the defunct URL
        . '&copy; 2007-' . gmdate('Y') . '</div>',
    );

    $PAGE->append(
        'USERBOX',
        $USER && $USER['id']
        ? $PAGE->meta(
            'userbox-logged-in',
            $PAGE->meta(
                'user-link',
                $USER['id'],
                $USER['group_id'],
                $USER['display_name'],
            ),
            $JAX->smalldate(
                $USER['last_visit'],
            ),
            $nummessages,
        )
        : $PAGE->meta('userbox-logged-out'),
    );
}


$PAGE->addvar('modlink', $PERMS['can_moderate'] ? $PAGE->meta('modlink') : '');

$PAGE->addvar('ismod', $PERMS['can_moderate'] ? 'true' : 'false');
$PAGE->addvar('isguest', $USER ? 'false' : 'true');
$PAGE->addvar('isadmin', $PERMS['can_access_acp'] ? 'true' : 'false');

$PAGE->addvar('acplink', $PERMS['can_access_acp'] ? $PAGE->meta('acplink') : '');
$PAGE->addvar('boardname', $CFG['boardname']);

if ($USER) {
    $PAGE->addvar('groupid', $JAX->pick($USER['group_id'], 3));
    $PAGE->addvar('userposts', $USER['posts']);
    $PAGE->addvar('grouptitle', $PERMS['title']);
    $PAGE->addvar('avatar', $JAX->pick($USER['avatar'], $PAGE->meta('default-avatar')));
    $PAGE->addvar('username', $USER['display_name']);
    $PAGE->addvar('userid', $JAX->pick($USER['id'], 0));
}

if (!isset($JAX->b['act'])) {
    $JAX->b['act'] = null;
}

if (
    $JAX->b['act'] !== 'logreg'
    && $JAX->b['act'] !== 'logreg2'
    && $JAX->b['act'] !== 'logreg4'
    && $JAX->b['act'] !== 'logreg3'
    && (
        !$PERMS['can_view_board']
        || $CFG['boardoffline']
        && !$PERMS['can_view_offline_board']
    )
) {
    $JAX->b['act'] = 'boardoffline';
}

// Include modules.
$modules = glob('jax/modules/*.php');
if ($modules) {
    foreach ($modules as $module) {
        $moduleName = pathinfo($module, PATHINFO_FILENAME);

        $module = $container->get('Jax\Modules\\' . $moduleName);

        if (
            property_exists($module, 'TAG') && !(!(
                isset($JAX->b['module'])
            && $JAX->b['module'] === $moduleName
            ) && !$PAGE->templatehas($moduleName))
        ) {
            continue;
        }

        $module->init();
    }
}

$actraw = isset($JAX->b['act']) ? mb_strtolower((string) $JAX->b['act']) : '';
preg_match('@^[a-zA-Z_]+@', $actraw, $act);
$act = array_shift($act);
$actdefs = [
    '' => 'idx',
    'vf' => 'forum',
    'vt' => 'topic',
    'vu' => 'userprofile',
];
if (isset($actdefs[$act])) {
    $act = $actdefs[$act];
}

if ($act === 'idx' && isset($JAX->b['module']) && $JAX->b['module']) {
    // Do nothing.
} elseif ($act && file_exists('jax/page/' . $act . '.php')) {
    $page = $container->get('Jax\Page\\' . $act);
    $page->route();
} elseif (!$PAGE->jsaccess || $PAGE->jsnewlocation) {
    $result = $DB->safeselect(
        ['page'],
        'pages',
        'WHERE `act`=?',
        $DB->basicvalue($actraw),
    );
    $page = $DB->arow($result);
    if ($page) {
        $DB->disposeresult($result);
        $page['page'] = $JAX->bbcodes($page['page']);
        $PAGE->append('PAGE', $page['page']);
        if ($PAGE->jsnewlocation) {
            $PAGE->JS('update', 'page', $page['page']);
        }
    } else {
        $PAGE->location('?act=idx');
    }
}

// Process temporary commands.
if ($PAGE->jsaccess && $SESS->runonce) {
    $PAGE->JSRaw($SESS->runonce);
    $SESS->runonce = '';
}

// Any changes to the session variables of the
// current user throughout the script are finally put into query form here.
$SESS->applyChanges();

$pagegen = '';

if ($onLocalHost) {
    $debug = '';

    $debug .= $PAGE->debug() . '<br>' . $DB->debug();
    $PAGE->JS('update', '#query .content', $debug);
    $PAGE->append(
        'FOOTER',
        $PAGE->collapsebox(
            'Debug',
            $debug,
            'query',
        ) . "<div id='debug2'></div><div id='pagegen'></div>",
    );
    $PAGE->JS(
        'update',
        'pagegen',
        $pagegen = 'Page Generated in '
            . round(1_000 * (microtime(true) - $microtime)) . ' ms',
    );
}

$PAGE->append(
    'DEBUG',
    "<div id='pagegen' style='text-align:center'>"
    . $pagegen
    . "</div><div id='debug' style='display:none'></div>",
);

if ($PAGE->jsnewlocation) {
    $PAGE->JS('title', htmlspecialchars_decode((string) $PAGE->get('TITLE'), ENT_QUOTES));
}

$PAGE->out();
