<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\DomainDefinitions;
use Jax\Models\Member;
use Jax\Request;
use Jax\Session;
use Jax\User;

use function header;

final readonly class Login
{
    public function __construct(
        private Config $config,
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
        ];

        if ($this->request->post('submit') !== null) {
            $user = $this->request->asString->post('user');
            $password = $this->request->asString->post('pass');

            $member = Member::selectOne('WHERE `name`=?', $user);
            $user = $member !== null
                ? $this->user->login($member->id, $password)
                : null;

            $error = match (true) {
                $user === null => 'The username/password supplied was incorrect',
                !$this->user->getGroup()?->canAccessACP => 'You are not authorized to log in to the ACP',
                default => null,
            };

            if ($error) {
                $pageElements['content'] = $this->page->error($error);
            }

            if ($user !== null) {
                // Successful login, redirect
                $this->session->setPHPSessionValue('auid', $user->id);
                header('Location: admin.php');

                return;
            }
        }

        echo $this->page->render(
            'login.html',
            $pageElements,
        );
    }
}
