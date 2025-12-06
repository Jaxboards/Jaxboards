<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;
use Jax\Models\Message;

use function dirname;
use function glob;
use function gmdate;
use function header;
use function implode;
use function json_decode;
use function json_encode;
use function mb_strtolower;
use function microtime;
use function pathinfo;
use function round;

use const JSON_FORCE_OBJECT;
use const JSON_OBJECT_AS_ARRAY;
use const JSON_THROW_ON_ERROR;
use const PATHINFO_FILENAME;

final readonly class App
{
    private float $microtime;

    public function __construct(
        private Config $config,
        private Container $container,
        private Date $date,
        private DebugLog $debugLog,
        private DomainDefinitions $domainDefinitions,
        private FileUtils $fileUtils,
        private IPAddress $ipAddress,
        private Page $page,
        private Request $request,
        private Router $router,
        private Session $session,
        private Template $template,
        private User $user,
    ) {
        $this->microtime = microtime(true);
    }

    public function render(): string
    {
        header('Cache-Control: no-cache, must-revalidate');

        if (!$this->config->hasInstalled()) {
            $this->page->location('./Service/install.php');
        }

        if (!$this->domainDefinitions->isBoardFound()) {
            return 'board not found';
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
        if ($this->request->isJSAccess() && $this->session->get()->runonce) {
            $this->page->commandsFromString($this->session->get()->runonce);
            $this->session->set('runonce', '');
        }

        // Any changes to the session variables of the
        // current user throughout the script are finally put into query form here.
        $this->session->applyChanges();

        if ($this->ipAddress->isLocalHost()) {
            $this->renderDebugInfo();
        }

        return $this->page->out();
    }

    private function startSession(): void
    {
        $userId = $this->session->loginWithToken();
        // Prefetch user data
        $this->user->login($userId);

        // Fix ip if necessary.
        if (
            !$this->user->isGuest()
            && $this->session->get()->ip
            && $this->session->get()->ip !== $this->user->get()->ip
        ) {
            $this->user->set('ip', $this->ipAddress->asBinary());
        }

        // "Login"
        // If they're logged in through cookies, (username & password)
        // but the session variable has changed/been removed/not updated for some reason
        // this fixes it.
        if (
            !$this->session->get()->isBot
            && $this->user->get()->id !== 0
            && $this->user->get()->id !== $this->session->get()->uid
        ) {
            $this->session->clean((int) $this->user->get()->id);
            $this->session->set('uid', $this->user->get()->id);
            $this->session->applychanges();
        }

        // If the user's navigated to a new page, change their action time.
        if (
            !$this->request->isJSNewLocation()
            && $this->request->isJSAccess()
        ) {
            return;
        }

        $this->session->act($this->request->asString->both('act'));
    }

    private function loadModules(): void
    {
        $modules = $this->fileUtils->glob('Jax/Modules/*.php');
        if (!$modules) {
            return;
        }

        foreach ($modules as $module) {
            $moduleName = pathinfo($module, PATHINFO_FILENAME);

            $module = $this->container->get('Jax\Modules\\' . $moduleName);

            $module->init();
        }
    }

    private function loadPageFromAction(): void
    {
        $action = mb_strtolower($this->request->asString->both('act') ?? '');

        if ($action === '' && $this->request->both('module') !== null) {
            return;
        }

        $this->router->route($action);
    }

    private function loadSkin(): void
    {
        $this->page->loadSkin(
            $this->session->getVar('skinID')
            ?: $this->user->get()->skinID,
        );
        $this->template->loadMeta('global');


        // Skin selector.
        if ($this->request->both('skinID') !== null) {
            if (!$this->request->both('skinID')) {
                $this->session->deleteVar('skinID');
                $this->page->command('reload');
            } else {
                $this->session->addVar('skinID', $this->request->both('skinID'));
                if ($this->request->isJSAccess()) {
                    $this->page->command('reload');
                }
            }
        }

        if (
            !$this->session->getVar('skinID')
        ) {
            return;
        }

        $this->page->append(
            'NAVIGATION',
            '<div class="success" '
            . 'style="position:fixed;bottom:0;left:0;width:100%;">'
            . 'Skin UCP setting being overridden. '
            . '<a href="?skinID=0">Revert</a></div>',
        );
    }

    private function renderBaseHTML(): void
    {
        $this->page->append(
            'SCRIPT',
            '<script src="' . $this->domainDefinitions->getBoardURL() . '/dist/app.js" defer></script>',
        );

        if (
            $this->user->getGroup()?->canModerate
            || $this->user->get()->mod
        ) {
            $this->page->append(
                'SCRIPT',
                '<script type="text/javascript" src="?act=modcontrols&do=load" defer></script>',
            );
        }

        if (
            $this->template->meta('favicon') !== ''
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
                $this->config->getSetting('logourl')
                ?: $this->domainDefinitions->getBoardURL() . '/Service/Themes/Default/img/logo.png',
            ),
        );
        $this->page->append(
            'NAVIGATION',
            $this->template->meta(
                'navigation',
                $this->user->getGroup()?->canModerate
                ? '<li><a href="?act=modcontrols&do=cp">Mod CP</a></li>' : '',
                $this->user->getGroup()?->canAccessACP
                ? '<li><a href="./ACP/" target="_BLANK">ACP</a></li>' : '',
                $this->config->getSetting('navlinks') ?? '',
            ),
        );
        $numMessages = 0;
        if ($this->user->get()->id !== 0) {
            $numMessages = Message::count(
                'WHERE `read`=0 AND `to`=?',
                $this->user->get()->id,
            );

            if ($numMessages) {
                $this->page->append(
                    'FOOTER',
                    '<a href="?act=ucp&what=inbox"><div id="notification" class="newmessage" '
                    . 'onclick="this.style.display=\'none\'">You have ' . $numMessages
                    . ' new message' . ($numMessages === 1 ? '' : 's') . '</div></a>',
                );
            }
        }

        $this->template->addVar('inbox', (string) $numMessages);

        $version = json_decode(
            $this->fileUtils->getContents(dirname(__DIR__) . '/composer.json') ?: '',
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
                    $this->user->get()->id,
                    $this->user->get()->groupID,
                    $this->user->get()->displayName,
                ),
                $this->date->smallDate(
                    $this->user->get()->lastVisit,
                ),
                $numMessages,
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
            ) . "<div id='pagegen' style='text-align: center'>{$pagegen}</div>",
        );
    }

    private function renderNavigation(): void
    {
        $this->page->setBreadCrumbs(['?' => ($this->config->getSetting('boardname') ?: 'Home')]);
    }

    private function setPageVars(): void
    {
        $this->template->addVar('ismod', $this->user->getGroup()?->canModerate ? 'true' : 'false');
        $this->template->addVar('isguest', $this->user->isGuest() ? 'true' : 'false');
        $this->template->addVar('isadmin', $this->user->getGroup()?->canAccessACP ? 'true' : 'false');

        $this->template->addVar(
            'modlink',
            $this->user->getGroup()?->canModerate
                ? $this->template->meta('modlink')
                : '',
        );

        $this->template->addVar(
            'acplink',
            $this->user->getGroup()?->canAccessACP
                ? $this->template->meta('acplink')
                : '',
        );
        $this->template->addVar('boardname', $this->config->getSetting('boardname'));

        $globalSettings = $this->user->isGuest() ? [] : [
            'canIM' => $this->user->getGroup()?->canIM,
            'groupID' => $this->user->get()->groupID,
            'soundIM' => $this->user->get()->soundIM,
            'userID' => $this->user->get()->id,
            'username' => $this->user->get()->displayName,
            'wysiwyg' => $this->user->get()->wysiwyg,
        ];

        $this->page->append(
            'SCRIPT',
            '<script>window.globalSettings=' . json_encode($globalSettings, JSON_FORCE_OBJECT) . '</script>',
        );

        if ($this->user->isGuest()) {
            return;
        }

        $this->template->addVar('groupid', (string) $this->user->get()->groupID);
        $this->template->addVar('userposts', (string) $this->user->get()->posts);
        $this->template->addVar('grouptitle', (string) $this->user->getGroup()?->title);
        $this->template->addVar('avatar', $this->user->get()->avatar ?: $this->template->meta('default-avatar'));
        $this->template->addVar('username', $this->user->get()->displayName);
        $this->template->addVar('userid', (string) $this->user->get()->id ?: '0');
    }
}
