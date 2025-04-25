<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;
use Exception;

use function array_pop;
use function array_shift;
use function file_exists;
use function file_get_contents;
use function glob;
use function gmdate;
use function header;
use function htmlspecialchars_decode;
use function in_array;
use function ini_set;
use function is_array;
use function json_decode;
use function json_encode;
use function mb_strtolower;
use function microtime;
use function pathinfo;
use function preg_match;
use function property_exists;
use function round;
use function session_start;
use function str_contains;

use const ENT_QUOTES;
use const JSON_OBJECT_AS_ARRAY;
use const JSON_THROW_ON_ERROR;
use const PATHINFO_FILENAME;

final class App
{
    private readonly float $microtime;

    private bool $onLocalHost = false;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly IPAddress $ipAddress,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Session $session,
        private readonly User $user,
        private readonly Container $container,
    ) {
        $this->onLocalHost = in_array($this->ipAddress->asHumanReadable(), ['127.0.0.1', '::1'], true);
        $this->microtime = microtime(true);
    }

    public function render(): void
    {
        header('Cache-Control: no-cache, must-revalidate');

        $this->connectDB();

        $this->startSession();

        $this->loadSkin();

        // Set Navigation.
        $this->renderNavigation();

        if (!$this->page->jsaccess) {
            $this->renderBaseHTML();
        }

        $this->setPageVars();

        $this->loadModules();

        $this->loadPageFromAction();

        // Process temporary commands.
        if ($this->page->jsaccess && $this->session->get('runonce')) {
            $this->page->JSRaw($this->session->get('runonce'));
            $this->session->set('runonce', '');
        }

        // Any changes to the session variables of the
        // current user throughout the script are finally put into query form here.
        $this->session->applyChanges();

        if ($this->onLocalHost) {
            $this->renderDebugInfo();
        }

        $this->page->out();
    }

    private function connectDB(): void
    {
        try {
            if ($this->onLocalHost) {
                $this->database->debugMode = true;
            }

            $this->database->connect(
                $this->config->getSetting('sql_host'),
                $this->config->getSetting('sql_username'),
                $this->config->getSetting('sql_password'),
                $this->config->getSetting('sql_db'),
                $this->config->getSetting('sql_prefix'),
            );
        } catch (Exception $e) {
            echo "Failed to connect to database. The following error was collected: <pre>{$e}</pre>";

            exit(1);
        }
    }

    private function startSession(): void
    {
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();

        // Board Service Stuff, get the board as specified by URL.
        // Initialize them.
        if ($this->config->getSetting('noboard')) {
            echo 'board not found';

            exit(1);
        }

        $userId = $this->session->loginWithToken();
        // Prefetch user data
        $this->user->getUser($userId);

        // Fix ip if necessary.
        if (
            !$this->user->isGuest()
            && $this->session->get('ip')
            && $this->session->get('ip') !== $this->user->get('ip')
        ) {
            $this->user->set('ip', $this->ipAddress->asBinary());
        }

        // "Login"
        // If they're logged in through cookies, (username & password)
        // but the session variable has changed/been removed/not updated for some reason
        // this fixes it.
        if (
            !$this->session->get('is_bot')
            && $this->user->get('id') !== $this->session->get('uid')
        ) {
            $this->session->clean($this->user->get('id'));
            $this->session->set('uid', $this->user->get('id'));
            $this->session->applychanges();
        }

        // If the user's navigated to a new page, change their action time.
        if (!$this->page->jsnewlocation && $this->page->jsaccess) {
            return;
        }

        $this->session->act($this->jax->b['act'] ?? null);
    }

    private function loadModules(): void
    {
        $modules = glob('jax/modules/*.php');
        if (!$modules) {
            return;
        }

        foreach ($modules as $module) {
            $moduleName = pathinfo($module, PATHINFO_FILENAME);

            $module = $this->container->get('Jax\Modules\\' . $moduleName);

            if (
                property_exists($module, 'TAG') && !(!(
                    isset($this->jax->b['module'])
                && $this->jax->b['module'] === $moduleName
                ) && !$this->page->templatehas($moduleName))
            ) {
                continue;
            }

            $module->init();
        }
    }

    private function loadPageFromAction(): void
    {
        $action = mb_strtolower($this->jax->b['act'] ?? '');

        // Handle board offline
        if (
            (
                !$this->user->getPerm('can_view_board')
                || $this->config->getSetting('boardoffline')
                && !$this->user->getPerm('can_view_offline_board')
            ) && !str_contains($action, 'logreg')
        ) {
            $action = 'boardoffline';
        }

        preg_match('@^[a-zA-Z_]+@', $action, $act);
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

        if (
            $act === 'idx'
            && isset($this->jax->b['module'])
            && $this->jax->b['module']
        ) {
            // Do nothing.
        } elseif ($act && file_exists('jax/page/' . $act . '.php')) {
            $page = $this->container->get('Jax\Page\\' . $act);
            $page->route();
        } elseif (!$this->page->jsaccess || $this->page->jsnewlocation) {
            $result = $this->database->safeselect(
                ['page'],
                'pages',
                'WHERE `act`=?',
                $this->database->basicvalue($action),
            );
            $page = $this->database->arow($result);
            if ($page) {
                $this->database->disposeresult($result);
                $textFormatting = $this->container->get(TextFormatting::class);
                $page['page'] = $textFormatting->bbcodes($page['page']);
                $this->page->append('PAGE', $page['page']);
                if ($this->page->jsnewlocation) {
                    $this->page->JS('update', 'page', $page['page']);
                }
            } else {
                $this->page->location('?act=idx');
            }
        }
    }

    private function loadSkin(): void
    {
        $this->page->loadskin(
            $this->jax->pick(
                $this->session->getVar('skin_id'),
                $this->user->get('skin_id'),
            ),
        );
        $this->page->loadmeta('global');


        // Skin selector.
        if (isset($this->jax->b['skin_id'])) {
            if (!$this->jax->b['skin_id']) {
                $this->session->deleteVar('skin_id');
                $this->page->JS('reload');
            } else {
                $this->session->addVar('skin_id', $this->jax->b['skin_id']);
                if ($this->page->jsaccess) {
                    $this->page->JS('reload');
                }
            }
        }

        if (
            !$this->session->getVar('skin_id')
        ) {
            return;
        }

        $this->page->append(
            'NAVIGATION',
            '<div class="success" '
            . 'style="position:fixed;bottom:0;left:0;width:100%;">'
            . 'Skin UCP setting being overridden. '
            . '<a href="?skin_id=0">Revert</a></div>',
        );
    }

    private function renderBaseHTML(): void
    {
        if (!$this->user->isGuest()) {
            $this->page->append(
                'SCRIPT',
                '<script>window.globalsettings='
                . json_encode([
                    'sound_im' => $this->user->get('sound_im') ? 1 : 0,
                    'wysiwyg' => $this->user->get('wysiwyg') ? 1 : 0,
                    'can_im' => $this->user->getPerm('can_im') ? 1 : 0,
                    'groupid' => $this->user->get('group_id'),
                    'username' => $this->user->get('display_name'),
                    'userid' => $this->user->get('id') ?? 0,
                ])
                . '</script>',
            );
        }

        $this->page->append(
            'SCRIPT',
            '<script src="' . BOARDURL . 'dist/app.js" defer></script>',
        );

        if ($this->user->getPerm('can_moderate') || $this->user->get('mod')) {
            $this->page->append(
                'SCRIPT',
                '<script type="text/javascript" src="?act=modcontrols&do=load" defer></script>',
            );
        }

        $this->page->append(
            'CSS',
            '<link rel="stylesheet" type="text/css" href="' . THEMEPATHURL . 'css.css">'
            . '<link rel="preload" as="style" type="text/css" href="./Service/wysiwyg.css" onload="this.onload=null;this.rel=\'stylesheet\'" />',
        );
        if (
            $this->page->meta('favicon') !== ''
            && $this->page->meta('favicon') !== '0'
        ) {
            $this->page->append(
                'CSS',
                '<link rel="icon" href="' . $this->page->meta('favicon') . '">',
            );
        }

        $this->page->append(
            'LOGO',
            $this->page->meta(
                'logo',
                $this->jax->pick(
                    $this->config->getSetting('logourl') ?? false,
                    BOARDURL . 'Service/Themes/Default/img/logo.png',
                ),
            ),
        );
        $this->page->append(
            'NAVIGATION',
            $this->page->meta(
                'navigation',
                $this->user->getPerm('can_moderate')
                ? '<li><a href="?act=modcontrols&do=cp">Mod CP</a></li>' : '',
                $this->user->getPerm('can_access_acp')
                ? '<li><a href="./acp/" target="_BLANK">ACP</a></li>' : '',
                $this->config->getSetting('navlinks') ?? '',
            ),
        );
        if ($this->user->get('id')) {
            $result = $this->database->safeselect(
                'COUNT(`id`)',
                'messages',
                'WHERE `read`=0 AND `to`=?',
                $this->user->get('id'),
            );
            $thisrow = $this->database->arow($result);
            $nummessages = 0;
            if (is_array($thisrow)) {
                $nummessages = array_pop($thisrow);
            }

            $this->database->disposeresult($result);
        }

        if (!isset($nummessages)) {
            $nummessages = 0;
        }

        $this->page->addvar('inbox', (string) $nummessages);
        if ($nummessages) {
            $this->page->append(
                'FOOTER',
                '<a href="?act=ucp&what=inbox"><div id="notification" class="newmessage" '
                . 'onclick="this.style.display=\'none\'">You have ' . $nummessages
                . ' new message' . ($nummessages === 1 ? '' : 's') . '</div></a>',
            );
        }

        $version = json_decode(
            file_get_contents(JAXBOARDS_ROOT . '/composer.json'),
            null,
            512,
            JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
        )['version'] ?? 'Unknown';

        $this->page->append(
            'FOOTER',
            '<div class="footer">'
            . "<a href=\"https://jaxboards.github.io\">Jaxboards</a> {$version}! "
            // Removed the defunct URL
            . '&copy; 2007-' . gmdate('Y') . '</div>',
        );

        $this->page->append(
            'USERBOX',
            $this->user->isGuest()
            ? $this->page->meta('userbox-logged-out')
            : $this->page->meta(
                'userbox-logged-in',
                $this->page->meta(
                    'user-link',
                    $this->user->get('id'),
                    $this->user->get('group_id'),
                    $this->user->get('display_name'),
                ),
                $this->jax->smalldate(
                    $this->user->get('last_visit'),
                ),
                $nummessages,
            ),
        );
    }

    private function renderDebugInfo(): void
    {
        $debug = '';

        $debug .= $this->page->debug() . '<br>' . $this->database->debug();
        $this->page->JS('update', '#query .content', $debug);
        $this->page->append(
            'FOOTER',
            $this->page->collapsebox(
                'Debug',
                $debug,
                'query',
            ) . "<div id='debug2'></div><div id='pagegen'></div>",
        );
        $this->page->JS(
            'update',
            'pagegen',
            $pagegen = 'Page Generated in '
                . round(1_000 * (microtime(true) - $this->microtime)) . ' ms',
        );
        $this->page->append(
            'DEBUG',
            "<div id='pagegen' style='text-align:center'>"
            . $pagegen
            . "</div><div id='debug' style='display:none'></div>",
        );
    }

    private function renderNavigation(): void
    {
        $this->page->path([$this->jax->pick($this->config->getSetting('boardname'), 'Home') => '?']);
        $this->page->append(
            'TITLE',
            $this->jax->pick(
                $this->page->meta('title'),
                $this->config->getSetting('boardname'),
                'JaxBoards',
            ),
        );
        if (!$this->page->jsnewlocation) {
            return;
        }

        $this->page->JS('title', htmlspecialchars_decode((string) $this->page->get('TITLE'), ENT_QUOTES));
    }

    private function setPageVars(): void
    {
        $this->page->addvar('modlink', $this->user->getPerm('can_moderate') ? $this->page->meta('modlink') : '');

        $this->page->addvar('ismod', $this->user->getPerm('can_moderate') ? 'true' : 'false');
        $this->page->addvar('isguest', $this->user->isGuest() ? 'true' : 'false');
        $this->page->addvar('isadmin', $this->user->getPerm('can_access_acp') ? 'true' : 'false');

        $this->page->addvar('acplink', $this->user->getPerm('can_access_acp') ? $this->page->meta('acplink') : '');
        $this->page->addvar('boardname', $this->config->getSetting('boardname'));

        if ($this->user->isGuest()) {
            return;
        }

        $this->page->addvar('groupid', (string) $this->jax->pick($this->user->get('group_id'), 3));
        $this->page->addvar('userposts', (string) $this->user->get('posts'));
        $this->page->addvar('grouptitle', $this->user->getPerm('title'));
        $this->page->addvar('avatar', $this->jax->pick($this->user->get('avatar'), $this->page->meta('default-avatar')));
        $this->page->addvar('username', $this->user->get('display_name'));
        $this->page->addvar('userid', (string) $this->jax->pick($this->user->get('id'), 0));
    }
}
