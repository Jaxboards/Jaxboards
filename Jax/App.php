<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;

use function array_pop;
use function dirname;
use function file_get_contents;
use function glob;
use function gmdate;
use function header;
use function implode;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function mb_strtolower;
use function microtime;
use function pathinfo;
use function property_exists;
use function round;

use const JSON_OBJECT_AS_ARRAY;
use const JSON_THROW_ON_ERROR;
use const PATHINFO_FILENAME;

final class App
{
    private readonly float $microtime;

    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
        private readonly Database $database,
        private readonly DebugLog $debugLog,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly IPAddress $ipAddress,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Router $router,
        private readonly Session $session,
        private readonly Template $template,
        private readonly User $user,
    ) {
        $this->microtime = microtime(true);
    }

    public function render(): void
    {
        header('Cache-Control: no-cache, must-revalidate');

        if (!$this->domainDefinitions->isBoardFound()) {
            echo 'board not found';

            return;
        }

        $this->startSession();

        $this->loadSkin();

        // Set Navigation.
        $this->renderNavigation();

        if (!$this->request->isJSAccess()) {
            $this->renderBaseHTML();
        }

        $this->setPageVars();

        $this->loadModules();

        $this->loadPageFromAction();

        // Process temporary commands.
        if ($this->request->isJSAccess() && $this->session->get('runonce')) {
            $this->page->commandsFromString($this->session->get('runonce'));
            $this->session->set('runonce', '');
        }

        // Any changes to the session variables of the
        // current user throughout the script are finally put into query form here.
        $this->session->applyChanges();

        $onLocalHost = in_array($this->ipAddress->asHumanReadable(), ['127.0.0.1', '::1'], true);
        if ($onLocalHost) {
            $this->renderDebugInfo();
        }

        $this->page->out();
    }

    private function startSession(): void
    {
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
        if (
            !$this->request->isJSNewLocation()
            && $this->request->isJSAccess()
        ) {
            return;
        }

        $this->session->act($this->request->both('act'));
    }

    private function loadModules(): void
    {
        $modules = glob('Jax/Modules/*.php');
        if (!$modules) {
            return;
        }

        foreach ($modules as $module) {
            $moduleName = pathinfo($module, PATHINFO_FILENAME);

            $module = $this->container->get('Jax\Modules\\' . $moduleName);

            if (
                property_exists($module, 'TAG')
                && $this->request->both('module') !== $moduleName
                && !$this->template->has($moduleName)
            ) {
                continue;
            }

            $module->init();
        }
    }

    private function loadPageFromAction(): void
    {
        $action = mb_strtolower($this->request->both('act') ?? '');

        if ($action === '' && $this->request->both('module') !== null) {
            return;
        }

        $this->router->route($action);
    }

    private function loadSkin(): void
    {
        $this->page->loadSkin(
            $this->jax->pick(
                $this->session->getVar('skin_id'),
                $this->user->get('skin_id'),
            ),
        );
        $this->template->loadMeta('global');


        // Skin selector.
        if ($this->request->both('skin_id') !== null) {
            if (!$this->request->both('skin_id')) {
                $this->session->deleteVar('skin_id');
                $this->page->command('reload');
            } else {
                $this->session->addVar('skin_id', $this->request->both('skin_id'));
                if ($this->request->isJSAccess()) {
                    $this->page->command('reload');
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
        $this->page->append(
            'SCRIPT',
            '<script src="' . $this->domainDefinitions->getBoardURL() . '/dist/app.js" defer></script>',
        );

        if ($this->user->getPerm('can_moderate') || $this->user->get('mod')) {
            $this->page->append(
                'SCRIPT',
                '<script type="text/javascript" src="?act=modcontrols&do=load" defer></script>',
            );
        }

        if (
            $this->template->meta('favicon') !== ''
            && $this->template->meta('favicon') !== '0'
        ) {
            $this->page->append(
                'CSS',
                '<link rel="icon" href="' . $this->template->meta('favicon') . '">',
            );
        }

        $this->page->append(
            'LOGO',
            $this->template->meta(
                'logo',
                $this->jax->pick(
                    $this->config->getSetting('logourl') ?? false,
                    $this->domainDefinitions->getBoardURL() . '/Service/Themes/Default/img/logo.png',
                ),
            ),
        );
        $this->page->append(
            'NAVIGATION',
            $this->template->meta(
                'navigation',
                $this->user->getPerm('can_moderate')
                ? '<li><a href="?act=modcontrols&do=cp">Mod CP</a></li>' : '',
                $this->user->getPerm('can_access_acp')
                ? '<li><a href="./ACP/" target="_BLANK">ACP</a></li>' : '',
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

        $this->template->addVar('inbox', (string) $nummessages);
        if ($nummessages) {
            $this->page->append(
                'FOOTER',
                '<a href="?act=ucp&what=inbox"><div id="notification" class="newmessage" '
                . 'onclick="this.style.display=\'none\'">You have ' . $nummessages
                . ' new message' . ($nummessages === 1 ? '' : 's') . '</div></a>',
            );
        }

        $version = json_decode(
            file_get_contents(dirname(__DIR__) . '/composer.json'),
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
            ? $this->template->meta('userbox-logged-out')
            : $this->template->meta(
                'userbox-logged-in',
                $this->template->meta(
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
        $debug = implode('<br>', $this->debugLog->getLog());
        $this->page->command('update', '#debug .content', $debug);
        $this->page->command(
            'update',
            'pagegen',
            $pagegen = 'Page Generated in '
                . round(1_000 * (microtime(true) - $this->microtime)) . ' ms',
        );
        $this->page->append(
            'DEBUG',
            $this->page->collapseBox(
                'Debug',
                $debug,
            ) . "<div id='pagegen' style='text-align: center'>{$pagegen}</div>"
        );
    }

    private function renderNavigation(): void
    {
        $this->page->setBreadCrumbs([$this->jax->pick($this->config->getSetting('boardname'), 'Home') => '?']);
    }

    private function setPageVars(): void
    {
        $this->template->addVar('modlink', $this->user->getPerm('can_moderate') ? $this->template->meta('modlink') : '');

        $this->template->addVar('ismod', $this->user->getPerm('can_moderate') ? 'true' : 'false');
        $this->template->addVar('isguest', $this->user->isGuest() ? 'true' : 'false');
        $this->template->addVar('isadmin', $this->user->getPerm('can_access_acp') ? 'true' : 'false');

        $this->template->addVar('acplink', $this->user->getPerm('can_access_acp') ? $this->template->meta('acplink') : '');
        $this->template->addVar('boardname', $this->config->getSetting('boardname'));

        if ($this->user->isGuest()) {
            return;
        }

        $this->template->addVar('groupid', (string) $this->jax->pick($this->user->get('group_id'), 3));
        $this->template->addVar('userposts', (string) $this->user->get('posts'));
        $this->template->addVar('grouptitle', $this->user->getPerm('title'));
        $this->template->addVar('avatar', $this->jax->pick($this->user->get('avatar'), $this->template->meta('default-avatar')));
        $this->template->addVar('username', $this->user->get('display_name'));
        $this->template->addVar('userid', (string) $this->jax->pick($this->user->get('id'), 0));

        $this->page->append(
            'SCRIPT',
            '<script>window.globalsettings='
            . json_encode([
                'can_im' => $this->user->getPerm('can_im'),
                'groupid' => $this->user->get('group_id'),
                'sound_im' => $this->user->get('sound_im'),
                'userid' => $this->user->get('id'),
                'username' => $this->user->get('display_name'),
                'wysiwyg' => $this->user->get('wysiwyg'),
            ])
            . '</script>',
        );
    }
}
