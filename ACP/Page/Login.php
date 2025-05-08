<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\Request;
use Jax\Session;
use Jax\User;

use function header;

final readonly class Login
{
    public function __construct(
        private Config $config,
        private Database $database,
        private DomainDefinitions $domainDefinitions,
        private Page $page,
        private Request $request,
        private User $user,
        private Session $session,
    ) {}

    public function render(): void
    {
        $boardUrl = $this->domainDefinitions->getBoardURL();
        $pageElements = [
            'board_name' => $this->config->getSetting('boardname'),
            'board_url' => $boardUrl,
            'content' => '',
            'css_url' => $boardUrl . '/ACP/css/login.css',
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
            $member = $this->database->arow($result);
            $user = $member
                ? $this->user->getUser($member['id'], $password)
                : null;
            $this->database->disposeresult($result);

            $error = match (true) {
                $user === null => 'The username/password supplied was incorrect',
                !$this->user->getPerm('can_access_acp') => 'You are not authorized to log in to the ACP',
                default => null,
            };

            if ($error === null) {
                // Successful login, redirect
                $this->session->setPHPSessionValue('auid', $user['id']);
                header('Location: admin.php');

                return;
            }

            $pageElements['content'] = $this->page->error($error);
        }

        echo $this->page->parseTemplate(
            'login.html',
            $pageElements,
        );
    }
}
