<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;
use Jax\Models\Message;

use function gmdate;
use function header;
use function implode;
use function json_decode;
use function json_encode;
use function mb_strtolower;
use function microtime;
use function round;

use const JSON_FORCE_OBJECT;
use const JSON_OBJECT_AS_ARRAY;
use const JSON_THROW_ON_ERROR;

final readonly class App
{
    private float $microtime;

    public function __construct(
        private Config $config,
        private Container $container,
        private Date $date,
        private DebugLog $debugLog,
        private DomainDefinitions $domainDefinitions,
        private FileSystem $fileSystem,
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
            $this->router->redirect('./Service/install.php');
        }

        if (!$this->domainDefinitions->isBoardFound()) {
            return 'board not found';
        }

        $this->startSession();

        $this->page->loadSkin();

        // Set Navigation.
        $this->renderNavigation();

        if (!$this->request->isJSAccess()) {
            $this->renderBaseHTML();
        }

        $this->setPageVars();

        $this->loadModules();

        $this->handleRouting();

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
        $modules = $this->fileSystem->glob('Jax/Modules/*.php');
        if ($modules === []) {
            return;
        }

        foreach ($modules as $module) {
            $fileInfo = $this->fileSystem->getFileInfo((string) $module);
            $moduleName = $fileInfo->getBasename('.' . $fileInfo->getExtension());

            $module = $this->container->get('Jax\Modules\\' . $moduleName);

            $module->init();
        }
    }

    private function handleRouting(): void
    {
        $action = mb_strtolower($this->request->asString->both('act') ?? '');

        if ($action === '' && $this->request->both('module') !== null) {
            return;
        }

        $this->router->route();
    }

    private function renderBaseHTML(): void
    {
        $this->page->append(
            'SCRIPT',
            '<script src="' . $this->domainDefinitions->getBoardURL() . '/dist/app.js" defer></script>',
        );

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
                $this->router->url('index'),
                $this->router->url('buddylist'),
                $this->router->url('search'),
                $this->router->url('members'),
                $this->router->url('ticker'),
                $this->router->url('calendar'),
                $this->user->getGroup()?->canModerate
                    ? '<li><a href="' . $this->router->url('modcontrols', ['do' => 'cp']) . '">Mod CP</a></li>'
                    : '',
                $this->user->getGroup()?->canAccessACP
                    ? '<li><a href="./ACP/" target="_blank">ACP</a></li>'
                    : '',
                $this->config->getSetting('navlinks') ?? '',
            ),
        );

        $unreadMessages = $this->getUnreadMessages();
        $this->renderFooter($unreadMessages);

        $this->page->append(
            'USERBOX',
            $this->user->isGuest()
                ? $this->template->meta(
                    'userbox-logged-out',
                    $this->router->url('forgotPassword'),
                    $this->router->url('register'),
                )
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
                    $unreadMessages,
                    $this->router->url('logout'),
                    $this->router->url('ucp', ['what' => 'inbox']),
                    $this->router->url('ucp'),
                ),
        );
    }

    private function getUnreadMessages(): int
    {
        if ($this->user->get()->id !== 0) {
            return Message::count(
                'WHERE `read`=0 AND `to`=?',
                $this->user->get()->id,
            );
        }

        return 0;
    }

    private function renderFooter(int $unreadMessages): void
    {
        if ($unreadMessages !== 0) {
            $inboxURL = $this->router->url('ucp', ['what' => 'inbox']);
            $plural = ($unreadMessages === 1 ? '' : 's');
            $this->page->append(
                'FOOTER',
                <<<HTML
                    <a href="{$inboxURL}">
                        <div id="notification" class="newmessage" onclick="this.style.display='none'">
                            You have {$unreadMessages} new message{$plural}
                        </div>
                    </a>
                    HTML,
            );
        }

        $version = json_decode(
            $this->fileSystem->getContents('composer.json') ?: '',
            null,
            flags: JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
        )['version'] ?? 'Unknown';

        $this->page->append(
            'FOOTER',
            '<div class="footer">'
                . "<a href=\"https://jaxboards.github.io\">Jaxboards</a> {$version}! "
                // Removed the defunct URL
                . '&copy; 2007-' . gmdate('Y') . '</div>',
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
        $this->page->setBreadCrumbs(
            [
                $this->router->url('index') => ($this->config->getSetting('boardname') ?: 'Home'),
            ],
        );
    }

    private function setPageVars(): void
    {
        $this->template->addVar('ismod', $this->user->getGroup()?->canModerate ? 'true' : 'false');
        $this->template->addVar('isguest', $this->user->isGuest() ? 'true' : 'false');
        $this->template->addVar('isadmin', $this->user->getGroup()?->canAccessACP ? 'true' : 'false');

        $this->template->addVar(
            'modlink',
            $this->user->getGroup()?->canModerate
                ? $this->template->meta(
                    'modlink',
                    $this->router->url('modcontrols', ['do' => 'cp']),
                )
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
