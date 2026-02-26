<?php

declare(strict_types=1);

namespace ACP\Routes;

use ACP\Page;
use Jax\Config;
use Jax\Interfaces\Route;
use Jax\Models\Member;
use Jax\Request;
use Jax\Router;
use Jax\Session;
use Jax\User;
use Override;

use function header;

final readonly class Login implements Route
{
    public function __construct(
        private Config $config,
        private Page $page,
        private Request $request,
        private Router $router,
        private User $user,
        private Session $session,
    ) {}

    #[Override]
    public function route(array $params): void
    {
        $rootUrl = $this->router->getRootURL();
        $pageElements = [
            'board_name' => $this->config->getSetting('boardname'),
            'board_url' => $rootUrl,
            'content' => '',
            'css_url' => $rootUrl . '/ACP/css/login.css',
        ];

        if ($this->request->post('submit') !== null) {
            $user = $this->request->asString->post('user');
            $password = $this->request->asString->post('pass');

            $member = Member::selectOne('WHERE `name`=?', $user);
            $user = $member !== null ? $this->user->login($member->id, $password) : null;

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

        echo $this->page->render('login.html', $pageElements);
    }
}
