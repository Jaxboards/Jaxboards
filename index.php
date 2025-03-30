<?php

/**
 * Jaxboards. THE ULTIMATE 4UMS WOOOOOOO
 * By Sean John's son (2007 @ 4 AM).
 *
 * PHP Version 8
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */

// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable
if ($_GET['showerrors'] ?? false) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
// phpcs:enable

if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', __DIR__);
}

header('Cache-Control: no-cache, must-revalidate');

$microtime = microtime(true);

// Load composer dependencies.
require_once JAXBOARDS_ROOT . '/vendor/autoload.php';

// Get the config.
const CONFIG_FILE = __DIR__ . '/config.php';
if (is_readable(CONFIG_FILE)) {
    require CONFIG_FILE;
}

if (!isset($CFG)) {
    fwrite(STDERR, 'missing configuration');

    http_response_code(500);

    exit(1);
}

// DB connect!
require_once __DIR__ . '/inc/classes/mysql.php';
$DB = new MySQL();
$connected = $DB->connect(
    $CFG['sql_host'],
    $CFG['sql_username'],
    $CFG['sql_password'],
    $CFG['sql_db'],
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
require_once __DIR__ . '/domaindefinitions.php';

// Require the classes.
require_once JAXBOARDS_ROOT . '/inc/classes/page.php';

require_once JAXBOARDS_ROOT . '/inc/classes/jax.php';

require_once JAXBOARDS_ROOT . '/inc/classes/sess.php';

// Initialize them.
if (isset($CFG['noboard']) && $CFG['noboard']) {
    echo 'board not found';

    exit(1);
}

$PAGE = new PAGE();
$JAX = new JAX();
// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable
$SESS = new SESS($_SESSION['sid'] ?? false);

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

// @phpstan-ignore-next-line property.notFound
if (!$SESS->is_bot && isset($_SESSION['uid']) && $_SESSION['uid']) {
    $JAX->getUser($_SESSION['uid']);
}
// phpcs:enable

// phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedAssigningByReference
$USER = &$JAX->userData;
// phpcs:enable
$PERMS = $JAX->getPerms();

// Fix ip if necessary.
// @phpstan-ignore-next-line property.notFound
if ($USER && $SESS->ip !== $USER['ip']) {
    $DB->safeupdate(
        'members',
        [
            // @phpstan-ignore-next-line property.notFound
            'ip' => $SESS->ip,
        ],
        'WHERE id=?',
        $USER['id'],
    );
}

// Load the theme.
// @phpstan-ignore-next-line method.notFound
$PAGE->loadskin(
    $JAX->pick(
        $SESS->vars['skin_id'] ?? false,
        $USER['skin_id'] ?? false,
    ),
);
// @phpstan-ignore-next-line method.notFound
$PAGE->loadmeta('global');

// Skin selector.
if (isset($JAX->b['skin_id'])) {
    if (!$JAX->b['skin_id']) {
        $SESS->delvar('skin_id');
        // @phpstan-ignore-next-line method.notFound
        $PAGE->JS('reload');
    } else {
        $SESS->addvar('skin_id', $JAX->b['skin_id']);
        // @phpstan-ignore-next-line property.notFound
        if ($PAGE->jsaccess) {
            // @phpstan-ignore-next-line method.notFound
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
if (
    $JAX->userData
    // @phpstan-ignore-next-line property.notFound
    && !$SESS->is_bot
    // @phpstan-ignore-next-line property.notFound
    && $JAX->userData['id'] !== $SESS->uid
) {
    $SESS->clean($USER['id']);
    // @phpstan-ignore-next-line property.notFound
    $SESS->uid = $USER['id'];
    $SESS->applychanges();
}

// If the user's navigated to a new page, change their action time.
if (
    // @phpstan-ignore-next-line property.notFound
    $PAGE->jsnewlocation
    // @phpstan-ignore-next-line property.notFound
    || !$PAGE->jsaccess
) {
    $SESS->act($JAX->b['act'] ?? null);
}

// Set Navigation.
// @phpstan-ignore-next-line method.notFound
$PAGE->path([$JAX->pick($CFG['boardname'], 'Home') => '?']);
$PAGE->append(
    'TITLE',
    $JAX->pick(
        // @phpstan-ignore-next-line method.notFound
        $PAGE->meta('title'),
        $CFG['boardname'],
        'JaxBoards',
    ),
);

// @phpstan-ignore-next-line property.notFound
if (!$PAGE->jsaccess) {
    $variables = [];
    foreach (['sound_im', 'wysiwyg'] as $v) {
        $variables[] = "{$v}:" . ($USER ? ($USER[$v] ? 1 : 0) : 1);
    }

    $variables[] = 'can_im:' . ($PERMS['can_im'] ? 1 : 0);
    if ($USER) {
        $variables[] = 'groupid:' . $JAX->pick($USER['group_id'], 3);
        $variables[] = "username:'" . addslashes((string) $USER['display_name']) . "'";
        $variables[] = 'userid:' . $JAX->pick($USER['id'], 0);
    }

    $PAGE->append(
        'SCRIPT',
        ' <script>var globalsettings={'
        . implode(',', $variables)
        . '}</script>',
    );
    $PAGE->append(
        'SCRIPT',
        ' <script src="' . BOARDURL
        . 'dist/app.js"></script>',
    );

    if ($USER && ($PERMS['can_moderate'] || $USER['mod'])) {
        $PAGE->append(
            'SCRIPT',
            '<script type="text/javascript" '
            . 'src="?act=modcontrols&do=load"></script>',
        );
    }

    $PAGE->append(
        'CSS',
        // @phpstan-ignore-next-line constant.notFound
        '<link rel="stylesheet" type="text/css" href="' . THEMEPATHURL
        . 'css.css">',
    );
    // @phpstan-ignore-next-line method.notFound
    if ($PAGE->meta('favicon')) {
        $PAGE->append(
            'CSS',
            // @phpstan-ignore-next-line method.notFound
            '<link rel="icon" href="' . $PAGE->meta('favicon') . '">',
        );
    }

    $PAGE->append(
        'LOGO',
        // @phpstan-ignore-next-line method.notFound
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
        // @phpstan-ignore-next-line method.notFound
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

    // @phpstan-ignore-next-line method.notFound
    $PAGE->addvar('inbox', $nummessages);
    if ($nummessages) {
        $PAGE->append(
            'FOOTER',
            '<a href="?act=ucp&what=inbox"><div id="notification" class="newmessage" '
            . 'onclick="this.style.display=\'none\'">You have ' . $nummessages
            . ' new message' . ($nummessages === 1 ? '' : 's') . '</div></a>',
        );
    }

    if (!isset($CFG['nocopyright']) || !$CFG['nocopyright']) {
        $PAGE->append(
            'FOOTER',
            '<div class="footer">'
            . 'Jaxboards 2.0.1! '
            // Removed the defunct URL
            . '&copy; 2007-' . date('Y') . '</div>',
        );
    }

    // @phpstan-ignore-next-line method.notFound
    $PAGE->addvar('modlink', $PERMS['can_moderate'] ? $PAGE->meta('modlink') : '');
    // @phpstan-ignore-next-line method.notFound
    $PAGE->addvar('ismod', $PERMS['can_moderate'] ? 1 : 0);
    // @phpstan-ignore-next-line method.notFound
    $PAGE->addvar('acplink', $PERMS['can_access_acp'] ? $PAGE->meta('acplink') : '');
    // @phpstan-ignore-next-line method.notFound
    $PAGE->addvar('isadmin', $PERMS['can_access_acp'] ? 1 : 0);
    // @phpstan-ignore-next-line method.notFound
    $PAGE->addvar('boardname', $CFG['boardname']);
    $PAGE->append(
        'USERBOX',
        $USER && $USER['id']
        // @phpstan-ignore-next-line method.notFound
        ? $PAGE->meta(
            'userbox-logged-in',
            // @phpstan-ignore-next-line method.notFound
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
        // @phpstan-ignore-next-line method.notFound
        : $PAGE->meta('userbox-logged-out'),
    );
}

// end if !jsaccess only
if ($USER) {
    // @phpstan-ignore-next-line method.notFound
    $PAGE->addvar('groupid', $JAX->pick($USER['group_id'], 3));
    // @phpstan-ignore-next-line method.notFound
    $PAGE->addvar('userposts', $USER['posts']);
    // @phpstan-ignore-next-line method.notFound
    $PAGE->addvar('grouptitle', $PERMS['title']);
    // @phpstan-ignore-next-line method.notFound
    $PAGE->addvar('avatar', $JAX->pick($USER['avatar'], $PAGE->meta('default-avatar')));
    // @phpstan-ignore-next-line method.notFound
    $PAGE->addvar('username', $USER['display_name']);
    // @phpstan-ignore-next-line method.notFound
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
$modules = glob('inc/modules/*.php');
if ($modules) {
    foreach ($modules as $v) {
        $m = [];
        if (preg_match('/tag_(\w+)/', $v, $m)) {
            if (
                (
                    isset($JAX->b['module'])
                    && $JAX->b['module'] === $m[1]
                    // @phpstan-ignore-next-line method.notFound
                ) || $PAGE->templatehas($m[1])
            ) {
                include $v;
            }
        } else {
            include $v;
        }
    }
}

// Looks like it's straight out of IPB, doesn't it?
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
} elseif ($act && is_file('inc/page/' . $act . '.php')) {
    $act = 'inc/page/' . $act . '.php';

    include_once $act;
} elseif (
    // @phpstan-ignore-next-line property.notFound
    !$PAGE->jsaccess
    // @phpstan-ignore-next-line property.notFound
    || $PAGE->jsnewlocation
) {
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
        // @phpstan-ignore-next-line property.notFound
        if ($PAGE->jsnewlocation) {
            // @phpstan-ignore-next-line method.notFound
            $PAGE->JS('update', 'page', $page['page']);
        }
    } else {
        $PAGE->location('?act=idx');
    }
}

// Process temporary commands.
// @phpstan-ignore-next-line property.notFound
if ($PAGE->jsaccess && $SESS->runonce) {
    // @phpstan-ignore-next-line method.notFound
    $PAGE->JSRaw($SESS->runonce);
    $SESS->runonce = '';
}

// Any changes to the session variables of the
// current user throughout the script are finally put into query form here.
$SESS->applyChanges();

$pagegen = '';

if (in_array($JAX->getIp(), ['127.0.0.1', '::1'], true)) {
    $debug = '';

    /** @phpstan-ignore-next-line method.notFound */
    $debug .= $PAGE->debug() . '<br>';
    // @phpstan-ignore-next-line method.notFound
    $PAGE->JS('update', '#query .content', $debug);
    $PAGE->append(
        'FOOTER',
        // @phpstan-ignore-next-line method.notFound
        $PAGE->collapsebox(
            'Debug',
            $debug,
            'query',
        ) . "<div id='debug2'></div><div id='pagegen'></div>",
    );
    // @phpstan-ignore-next-line method.notFound
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

// @phpstan-ignore-next-line property.notFound
if ($PAGE->jsnewlocation) {
    // @phpstan-ignore-next-line method.notFound
    $PAGE->JS('title', htmlspecialchars_decode($PAGE->get('TITLE'), ENT_QUOTES));
}

$PAGE->out();
