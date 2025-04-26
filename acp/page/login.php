<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\Request;
use Jax\User;

use function header;
use function ini_set;
use function session_start;

final readonly class Login
{
    public function __construct(
        private Config $config,
        private Database $database,
        private DomainDefinitions $domainDefinitions,
        private Page $page,
        private Request $request,
        private User $user,
    ) {}

    public function render(): void
    {
        $this->startSession();

        $boardUrl = $this->domainDefinitions->getBoardURL();
        $pageElements = [
            'board_name' => $this->config->getSetting('boardname'),
            'board_url' => $boardUrl,
            'content' => '',
            'css_url' => $boardUrl . '/acp/css/login.css',
            'favicon_url' => $boardUrl . '/favicon.ico',
        ];

        if ($this->request->post('submit') !== null) {
            $user = $this->request->post('user');
            $password = $this->request->post('pass');

            $result = $this->database->safeselect(
                ['id'],
                'members',
                'WHERE `name`=?',
                $user,
            );
            $member = $this->database->row($result);
            $user = $member
                ? $this->user->getUser($member['id'], $password)
                : null;
            $this->database->disposeresult($result);

            if ($user === null) {
                $pageElements['content'] = $this->page->error(
                    'The username/password supplied was incorrect',
                );
            } elseif (!$this->user->getPerm('can_access_acp')) {
                $pageElements['content'] = $this->page->error(
                    'You are not authorized to log in to the ACP',
                );
            } else {
                $_SESSION['auid'] = $user['id'];
                // Successful login, redirect
                header('Location: admin.php');
            }
        }

        echo $this->page->parseTemplate(
            'login.html',
            $pageElements,
        );
    }

    private function startSession(): void
    {
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_cookies', 1);
        ini_set('session.use_only_cookies', 1);
        session_start();
    }
}
