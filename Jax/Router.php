<?php

namespace Jax;

use DI\Container;

class Router {
    private $staticRoutes = [
        'asteroids' => '\Jax\Page\Asteroids',
        'boardoffline' => '\Jax\Page\BoardOffline',
        'idx' => '\Jax\Page\IDX',
        'buddylist' => '\Jax\Page\BuddyList',
        'calendar' => '\Jax\Page\Calendar',
        'download' => '\Jax\Page\Download',
        'earthbound' => '\Jax\Page\Earthbound',
        'katamari' => '\Jax\Page\Katamari',
        'members' => '\Jax\Page\Members',
        'modcontrols' => '\Jax\Page\ModControls',
        'post' => '\Jax\Page\Post',
        'rainbow' => '\Jax\Page\Rainbow',
        'search' => '\Jax\Page\Search',
        'tardis' => '\Jax\Page\Tardis',
        'ticker' => '\Jax\Page\Ticker',
        'topic' => '\Jax\Page\Topic',
        'ucp' => '\Jax\Page\UCP',
        '' => '\Jax\Page\IDX',
    ];

    private $dynamicRoutes = [
        'vf' => '\Jax\Page\Forum',
        'vt' => '\Jax\Page\Topic',
        'vu' => '\Jax\Page\UserProfile',
        'logreg' => '\Jax\Page\LogReg',
    ];

    public function __construct(
        private Request $request,
        private Config $config,
        private Container $container,
        private Database $database,
        private Page $page,
        private User $user,
    ){}

    public function route(string $action) {
        $pageClassName = null;

        // Board offline
        if ($this->isBoardOffline() && !str_contains($action, 'logreg')) {
            $pageClassName = '\Jax\Page\BoardOffline';

        // Static Routes
        } elseif (array_key_exists($action, $this->staticRoutes)) {
            $pageClassName = $this->staticRoutes[$action];

        // Dynamic Routes
        } else {
            preg_match('@^[a-zA-Z_]+@', $action, $act);

            $act = array_shift($act);
            if (isset($this->dynamicRoutes[$act])) {
                $pageClassName = $this->dynamicRoutes[$act];
            }

        }

        if ($pageClassName !== null) {
            $this->container->get($pageClassName)->render();
            return;
        }

        // Handle custom pages
        if (
            !$this->request->isJSAccess()
            || $this->request->isJSNewLocation()
        ) {
            $result = $this->database->safeselect(
                ['page'],
                'pages',
                'WHERE `act`=?',
                $this->database->basicvalue($action),
            );
            $page = $this->database->arow($result);

            if ($page) {
                $this->database->disposeresult($result);
                $textFormatting = $this->container->get(TextFormatting::class);
                $pageContents = $textFormatting->bbcodes($page['page']);
                $this->page->append('PAGE', $pageContents);
                if ($this->request->isJSNewLocation()) {
                    $this->page->JS('update', 'page', $pageContents);
                }
                return;
            }

            $this->page->location('?act=idx');
        }
    }

    private function isBoardOffline() {
        return (
            !$this->user->getPerm('can_view_board')
            || $this->config->getSetting('boardoffline')
            && !$this->user->getPerm('can_view_offline_board')
        );
    }
}
