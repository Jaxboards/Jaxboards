<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\User;

use function header;

final readonly class Login
{
    public function __construct(
        private Config $config,
        private Database $database,
        private Jax $jax,
        private Page $page,
        private User $user,
    ) {}

    public function render(): void
    {
        $pageElements = [
            'board_name' => $this->config->getSetting('boardname'),
            'board_url' => BOARDURL,
            'content' => '',
            'css_url' => BOARDURL . 'acp/css/login.css',
            'favicon_url' => BOARDURL . 'favicon.ico',
        ];

        if (isset($this->jax->p['submit'])) {
            $user = $this->jax->p['user'];
            $password = $this->jax->p['pass'];

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
}
