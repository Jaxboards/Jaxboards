<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;
use Jax\Models\Message;
use Jax\Models\Report;

use function gmdate;
use function header;
use function implode;
use function json_decode;
use function json_encode;
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

        $this->setPageMetadata();

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
        if (!$this->request->isJSNewLocation() && $this->request->isJSAccess()) {
            return;
        }

        $this->session->act();
    }

    private function loadModules(): void
    {
        $modules = $this->fileSystem->glob('src/Jax/Modules/*.php');
        if ($modules === []) {
            return;
        }

        foreach ($modules as $module) {
            $fileInfo = $this->fileSystem->getFileInfo($module);
            $moduleName = $fileInfo->getBasename('.' . $fileInfo->getExtension());

            $module = $this->container->get('Jax\Modules\\' . $moduleName);

            $module->init();
        }
    }

    private function handleRouting(): void
    {
        if ($this->request->both('module') !== null) {
            return;
        }

        // Redirect to index instead of 404
        if ($this->router->route($this->request->asString->both('path') ?? '')) {
            return;
        }

        $this->router->redirect('index');
    }

    private function renderBaseHTML(): void
    {
        $timestamp = $this->fileSystem->getFileInfo('public/assets/app.js')->getMTime();
        $this->page->append(
            'SCRIPT',
            '<script src="'
            . $this->router->getRootURL()
            . "/assets/app.js?{$timestamp}\" defer></script>"
            . '<script src="https://kit.fontawesome.com/69affb3f61.js" crossorigin="anonymous"></script>',
        );

        $this->page->append('LOGO', $this->template->render('global/logo', [
            'logoURL' => $this->config->getSetting('logourl')
                ?: $this->router->getRootURL() . '/Service/Themes/Default/img/logo.png',
        ]));
        $this->page->append('NAVIGATION', $this->template->render('global/navigation', [
            'perms' => $this->user->getGroup(),
        ]));

        $unreadMessages = $this->getUnreadMessages();
        $this->renderFooter($unreadMessages);

        $this->page->append(
            'USERBOX',
            $this->user->isGuest()
                ? $this->template->render('global/userbox-logged-out')
                : $this->template->render('global/userbox-logged-in', [
                    'user' => $this->user->get(),
                    'lastVisit' => $this->date->smallDate($this->user->get()->lastVisit),
                    'unreadMessages' => $unreadMessages,
                    'notificationCount' => 0,
                ]),
        );
    }

    private function getUnreadMessages(): int
    {
        if (!$this->user->isGuest()) {
            return Message::count('WHERE `read`=0 AND `to`=?', $this->user->get()->id);
        }

        return 0;
    }

    private function renderFooter(int $unreadMessages): void
    {
        if ($unreadMessages !== 0) {
            $this->page->append('FOOTER', $this->template->render('notifications/unread-messages', [
                'unreadMessages' => $unreadMessages,
            ]));
        }

        if ($this->user->isModerator()) {
            $reportCount = Report::count('WHERE `acknowledger` IS NULL');
            if ($reportCount !== 0) {
                $this->page->append('FOOTER', $this->template->render('notifications/post-reports', [
                    'reportCount' => $reportCount,
                ]));
            }
        }

        $version =
            json_decode(
                $this->fileSystem->getContents('composer.json') ?: '',
                null,
                flags: JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
            )['version'] ?? 'Unknown';

        $this->page->append(
            'FOOTER',
            '<div class="footer">'
            . "<a href=\"https://jaxboards.upgraded.click\">Jaxboards</a> {$version}! "
            // Removed the defunct URL
            . '&copy; 2007-'
            . gmdate('Y')
            . '</div>',
        );
    }

    private function renderDebugInfo(): void
    {
        $debug = implode('<br>', $this->debugLog->getLog());
        $this->page->command('update', '#debug .content', $debug);
        $this->page->command(
            'update',
            'pagegen',
            $pagegen = 'Page Generated in ' . round(1_000 * (microtime(true) - $this->microtime)) . ' ms',
        );
        $this->page->append(
            'DEBUG',
            $this->page->collapseBox('Debug', $debug, 'debug')
                . "<div id='pagegen' style='text-align: center'>{$pagegen}</div>",
        );
    }

    private function setPageMetadata(): void
    {
        $this->page->setOpenGraphData([
            'site_name' => $this->config->getSetting('boardname'),
            'type' => 'website',
        ]);

        $this->page->setBreadCrumbs([
            $this->router->url('index') => $this->config->getSetting('boardname') ?: 'Home',
        ]);
    }

    private function setPageVars(): void
    {
        $globalSettings = $this->user->isGuest()
            ? []
            : [
                'canIM' => $this->user->getGroup()?->canIM,
                'isAdmin' => $this->user->getGroup()?->canAccessACP,
                'isMod' => $this->user->getGroup()?->canModerate,
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
    }
}
