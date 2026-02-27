<?php

declare(strict_types=1);

namespace ACP;

use Jax\Config;
use Jax\DebugLog;
use Jax\IPAddress;
use Jax\Request;
use Jax\Router;
use Jax\Session;
use Jax\User;

use function ACP\routes;
use function implode;

/**
 * Admin control panel.
 *
 * PHP Version 5.3.7
 *
 * @see https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */
final readonly class App
{
    public function __construct(
        private Config $config,
        private DebugLog $debugLog,
        private IPAddress $ipAddress,
        private Nav $nav,
        private Page $page,
        private Request $request,
        private Router $router,
        private User $user,
        private Session $session,
    ) {
        routes($router);
    }

    public function render(): void
    {
        if (!$this->hasACPAccess()) {
            $this->page->location('./');

            return;
        }

        $this->page->append('username', $this->user->get()->displayName);
        $this->page->append('title', $this->config->getSetting('boardname') . ' - ACP');

        $this->nav->render();

        $this->router->route($this->request->asString->both('path') ?? '');

        if ($this->ipAddress->isLocalHost()) {
            $this->page->addContentBox('Debug', implode('<br>', $this->debugLog->getLog()));
        }

        $this->page->out();
    }

    private function hasACPAccess(): bool
    {
        $adminUserId = $this->session->getPHPSessionValue('auid');
        if ($adminUserId) {
            $this->user->login($adminUserId);
        }

        return (bool) $this->user->getGroup()?->canAccessACP;
    }
}
