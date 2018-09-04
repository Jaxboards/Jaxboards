<?php
/**
 * Jaxboards. THE ULTIMATE 4UMS WOOOOOOO
 * By Sean John's son (2007 @ 4 AM).
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
if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', __DIR__);
}

header('Cache-Control: no-cache, must-revalidate');

$local = '127.0.0.1' == $_SERVER['REMOTE_ADDR'];
$microtime = microtime(true);

// This is the best place to load the password compatibility library,
// so do it here.
if (!function_exists('password_hash')) {
    include_once JAXBOARDS_ROOT . '/inc/lib/password.php';
}

// Get the config.
require 'config.php';

// DB connect!
require_once 'inc/classes/mysql.php';
$DB = new MySQL();
$connected = $DB->connect(
    $CFG['sql_host'],
    $CFG['sql_username'],
    $CFG['sql_password'],
    $CFG['sql_db']
);
if (!$connected) {
    die('Could not connect');
}

// Start a session.
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Board Service Stuff, get the board as specified by URL.
require_once 'domaindefinitions.php';

// Require the classes.
require_once JAXBOARDS_ROOT . '/inc/classes/page.php';
require_once JAXBOARDS_ROOT . '/inc/classes/jax.php';
require_once JAXBOARDS_ROOT . '/inc/classes/sess.php';

// Initialize them.
if (isset($CFG['noboard']) && $CFG['noboard']) {
    die('board not found');
}

$PAGE = new PAGE();
$JAX = new JAX();
$SESS = new SESS(isset($_SESSION['sid']) ? $_SESSION['sid'] : false);

if (!isset($_SESSION['uid']) && isset($JAX->c['utoken'])) {
    $result = $DB->safeselect(
        '`uid`',
        'tokens',
        'WHERE `token`=?',
        $JAX->c['utoken']
    );
    $token = $DB->arow($result);
    if ($token) {
        $_SESSION['uid'] = $token['uid'];
    }
}
if (!$SESS->is_bot && isset($_SESSION['uid']) && $_SESSION['uid']) {
    $JAX->getUser($_SESSION['uid']);
}

$USER = &$JAX->userData;
$PERMS = $JAX->getPerms();

// Fix ip if necessary.
if ($USER && $SESS->ip != $USER['ip']) {
    $DB->safeupdate(
        'members',
        array(
            'ip' => $SESS->ip,
        ),
        'WHERE id=?',
        $USER['id']
    );
}

// Load the theme.
$PAGE->loadskin(
    $JAX->pick(
        isset($SESS->vars['skin_id']) ? $SESS->vars['skin_id'] : false,
        isset($USER['skin_id']) ? $USER['skin_id'] : false
    )
);
$PAGE->loadmeta('global');

// Skin selector.
if (isset($JAX->b['skin_id'])) {
    if (!$JAX->b['skin_id']) {
        $SESS->delvar('skin_id');
        $PAGE->JS('script', "document.location='?'");
    } else {
        $SESS->addvar('skin_id', $JAX->b['skin_id']);
        if ($PAGE->jsaccess) {
            $PAGE->JS('script', "document.location='?'");
        }
    }
}
if (isset($SESS->vars['skin_id']) && $SESS->vars['skin_id']) {
    $PAGE->append(
        'NAVIGATION',
        '<div class="success" ' .
        'style="position:fixed;bottom:0;left:0;width:100%;">' .
        'Skin UCP setting being overriden. ' .
        '<a href="?skin_id=0">Revert</a></div>'
    );
}

// "Login"
// If they're logged in through cookies, (username & password)
// but the session variable has changed/been removed/not updated for some reason
// this fixes it.
if ($JAX->userData && !$SESS->is_bot) {
    if ($JAX->userData['id'] != $SESS->uid) {
        $SESS->clean($USER['id']);
        $SESS->uid = $USER['id'];
        $SESS->applychanges();
    }
}

// If the user's navigated to a new page, change their action time.
if ($PAGE->jsnewlocation || !$PAGE->jsaccess) {
    $SESS->act(isset($JAX->b['act']) ? $JAX->b['act'] : null);
}

// Set Navigation.
$PAGE->path(array($JAX->pick($CFG['boardname'], 'Home') => '?'));
$PAGE->append(
    'TITLE',
    $JAX->pick(
        $PAGE->meta('title'),
        $CFG['boardname'],
        'JaxBoards'
    )
);

if (!$PAGE->jsaccess) {
    foreach (array('sound_im', 'wysiwyg') as $v) {
        $variables[] = "${v}:" . ($USER ? ($USER[$v] ? 1 : 0) : 1);
    }
    $variables[] = 'can_im:' . ($PERMS['can_im'] ? 1 : 0);
    $variables[] = 'groupid:' . ($JAX->pick($USER['group_id'], 3));
    $variables[] = "username:'" . addslashes($USER['display_name']) . "'";
    $variables[] = 'userid:' . $JAX->pick($USER['id'], 0);

    $PAGE->append(
        'SCRIPT',
        ' <script type="text/javascript">var globalsettings={' .
        implode(',', $variables) .
        '}</script>'
    );
    $PAGE->append(
        'SCRIPT',
        ' <script type="text/javascript" src="' . BOARDURL .
        'Service/jsnew.js"></script>'
    );
    $PAGE->append(
        'SCRIPT',
        ' <script type="text/javascript" src="' . BOARDURL .
        'Service/jsrun.js"></script>'
    );

    if ($PERMS['can_moderate'] || $USER['mod']) {
        $PAGE->append(
            'SCRIPT',
            '<script type="text/javascript" ' .
            'src="?act=modcontrols&do=load"></script>'
        );
    }

    $PAGE->append(
        'CSS',
        '<link rel="stylesheet" type="text/css" href="' . THEMEPATHURL .
        'css.css" />'
    );
    if ($PAGE->meta('favicon')) {
        $PAGE->append(
            'CSS',
            '<link rel="icon" href="' . $PAGE->meta('favicon') . '">'
        );
    }
    $PAGE->append(
        'LOGO',
        $PAGE->meta(
            'logo',
            $JAX->pick(
                isset($CFG['logourl']) ? $CFG['logourl'] : false,
                BOARDURL . 'Service/Themes/Default/img/logo.png'
            )
        )
    );
    $PAGE->append(
        'NAVIGATION',
        $PAGE->meta(
            'navigation',
            $PERMS['can_moderate'] ?
            '<li><a href="?act=modcontrols&do=cp">Mod CP</a></li>' : '',
            $PERMS['can_access_acp'] ?
            '<li><a href="./acp/" target="_BLANK">ACP</a></li>' : '',
            isset($CFG['navlinks']) && $CFG['navlinks'] ? $CFG['navlinks'] : ''
        )
    );
    if ($USER && $USER['id']) {
        $result = $DB->safeselect(
            'COUNT(`id`)',
            'messages',
            'WHERE `read`=0 AND `to`=?',
            $USER['id']
        );
        $thisrow = $DB->arow($result);
        $nummessages = array_pop($thisrow);
        $DB->disposeresult($result);
    }
    if (!isset($nummessages)) {
        $nummessages = 0;
    }
    $PAGE->addvar('inbox', $nummessages);
    if ($nummessages) {
        $PAGE->append(
            'FOOTER',
            '<div id="notification" class="newmessage" ' .
            'onclick="RUN.stream.location(\'?act=ucp&what=inbox\');' .
            'this.style.display=\'none\'">You have ' . $nummessages .
            ' new message' . (1 == $nummessages ? '' : 's') . '</div>'
        );
    }
    if (!isset($CFG['nocopyright']) || !$CFG['nocopyright']) {
        $PAGE->append(
            'FOOTER',
            '<div class="footer">' .
            '<a href="http://jaxboards.com">Jaxboards 1.1.0</a> ' .
            '&copy; 2007-' . date('Y') . '</div>'
        );
    }
    $PAGE->addvar('modlink', $PERMS['can_moderate'] ? $PAGE->meta('modlink') : '');
    $PAGE->addvar('ismod', $PERMS['can_moderate'] ? 1 : 0);
    $PAGE->addvar('acplink', $PERMS['can_access_acp'] ? $PAGE->meta('acplink') : '');
    $PAGE->addvar('isadmin', $PERMS['can_access_acp'] ? 1 : 0);
    $PAGE->addvar('boardname', $CFG['boardname']);
    $PAGE->append(
        'USERBOX',
        $USER['id'] ? $PAGE->meta(
            'userbox-logged-in',
            $PAGE->meta(
                'user-link',
                $USER['id'],
                $USER['group_id'],
                $USER['display_name']
            ),
            $JAX->smalldate(
                $USER['last_visit']
            ),
            $nummessages
        ) : $PAGE->meta('userbox-logged-out')
    );
} //end if !jsaccess only
$PAGE->addvar('groupid', $JAX->pick($USER['group_id'], 3));
$PAGE->addvar('userposts', $USER['posts']);
$PAGE->addvar('grouptitle', $PERMS['title']);
$PAGE->addvar('avatar', $JAX->pick($USER['avatar'], $PAGE->meta('default-avatar')));
$PAGE->addvar('username', $USER['display_name']);
$PAGE->addvar('userid', $JAX->pick($USER['id'], 0));

if (!isset($JAX->b['act'])) {
    $JAX->b['act'] = null;
}
if ('logreg' != $JAX->b['act']
    && 'logreg2' != $JAX->b['act']
    && 'logreg4' != $JAX->b['act']
    && 'logreg3' != $JAX->b['act']
) {
    if (!$PERMS['can_view_board']
        || $CFG['boardoffline']
        && !$PERMS['can_view_offline_board']
    ) {
        $JAX->b['act'] = 'boardoffline';
    }
}

// Include modules.
foreach (glob('inc/modules/*.php') as $v) {
    if (preg_match('/tag_(\\w+)/', $v, $m)) {
        if (isset($m[1]) && ((isset($JAX->b['module'])
            && $JAX->b['module'] == $m[1]) || $PAGE->templatehas($m[1]))
        ) {
            include $v;
        }
    } elseif (preg_match('/cookie_(\\w+)/', $v, $m)) {
        if ((isset($JAX->b['module'])
            && $JAX->b['module'] == $m[1])
            || (isset($m[1], $JAX->c[$m[1]])
            && $JAX->c[$m[1]])
        ) {
            include $v;
        }
    } else {
        include $v;
    }
}

// Looks like it's straight out of IPB, doesn't it?
$actraw = isset($JAX->b['act']) ? mb_strtolower($JAX->b['act']) : '';
preg_match('@^[a-zA-Z_]+@', $actraw, $act);
$act = array_shift($act);
$actdefs = array(
    '' => 'idx',
    'vf' => 'forum',
    'vt' => 'topic',
    'vu' => 'userprofile',
);
if (isset($actdefs[$act]) && $actdefs[$act]) {
    $act = $actdefs[$act];
}
if ('idx' == $act && isset($JAX->b['module']) && $JAX->b['module']) {
    // Do nothing.
} elseif ($act && is_file($act = 'inc/page/' . $act . '.php')) {
    include_once $act;
} elseif (!$PAGE->jsaccess || $PAGE->jsnewlocation) {
    $result = $DB->safeselect(
        '`page`',
        'pages',
        'WHERE `act`=?',
        $DB->basicvalue($actraw)
    );
    if ($page = $DB->arow($result)) {
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

// Keeps people from leaving their windows open all night.
if (($SESS->last_update - $SESS->last_action) > 1200) {
    $PAGE->JS('script', 'window.name=Math.random()');
}
// Any changes to the session variables of the
// current user throughout the script are finally put into query form here.
$SESS->applyChanges();

if (in_array($JAX->getIp(), array('127.0.0.1', '::1'))) {
    $debug = '';
    foreach ($DB->queryRuntime as $k => $v) {
        $debug .= "<b>${v}</b> " . $DB->queryList[$k] . '<br />';
        $qtime += $v;
    }
    $debug .= $PAGE->debug() . '<br />';
    $PAGE->JS('update', '#query .content', $debug);
    $PAGE->append(
        'FOOTER',
        $PAGE->collapsebox(
            'Debug',
            $debug,
            'query'
        ) . "<div id='debug2'></div><div id='pagegen'></div>"
    );
    $PAGE->JS(
        'update',
        'pagegen',
        $pagegen = 'Page Generated in ' .
        round(1000 * (microtime(true) - $microtime)) . ' ms'
    );
}
$PAGE->append(
    'DEBUG',
    "<div id='pagegen' style='text-align:center'>" .
    $pagegen .
    "</div><div id='debug' style='display:none'></div>"
);

if ($PAGE->jsnewlocation) {
    $PAGE->JS('title', htmlspecialchars_decode($PAGE->get('TITLE'), ENT_QUOTES));
}
$PAGE->out();
