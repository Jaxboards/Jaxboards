<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;
use Jax\Page\Asteroids;
use Jax\Page\BoardOffline;
use Jax\Page\BuddyList;
use Jax\Page\Calendar;
use Jax\Page\Download;
use Jax\Page\Earthbound;
use Jax\Page\Forum;
use Jax\Page\IDX;
use Jax\Page\Katamari;
use Jax\Page\LogReg;
use Jax\Page\Members;
use Jax\Page\ModControls;
use Jax\Page\Post;
use Jax\Page\Rainbow;
use Jax\Page\Search;
use Jax\Page\Tardis;
use Jax\Page\Ticker;
use Jax\Page\Topic;
use Jax\Page\UCP;
use Jax\Page\UserProfile;

use function array_key_exists;
use function array_shift;
use function preg_match;
use function str_contains;

final class Router
{
    private $staticRoutes = [
        'asteroids' => Asteroids::class,
        'boardoffline' => BoardOffline::class,
        'idx' => IDX::class,
        'buddylist' => BuddyList::class,
        'calendar' => Calendar::class,
        'download' => Download::class,
        'earthbound' => Earthbound::class,
        'katamari' => Katamari::class,
        'members' => Members::class,
        'modcontrols' => ModControls::class,
        'post' => Post::class,
        'rainbow' => Rainbow::class,
        'search' => Search::class,
        'tardis' => Tardis::class,
        'ticker' => Ticker::class,
        'topic' => Topic::class,
        'ucp' => UCP::class,
        '' => IDX::class,
    ];

    private $dynamicRoutes = [
        'vf' => Forum::class,
        'vt' => Topic::class,
        'vu' => UserProfile::class,
        'logreg' => LogReg::class,
    ];

    public function __construct(
        private readonly Request $request,
        private readonly Config $config,
        private readonly Container $container,
        private readonly Database $database,
        private readonly Page $page,
        private readonly User $user,
    ) {}

    public function route(string $action): void
    {
        $pageClassName = null;

        // Board offline
        if ($this->isBoardOffline() && !str_contains($action, 'logreg')) {
            $pageClassName = BoardOffline::class;

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
            $this->request->isJSAccess()
            && !$this->request->isJSNewLocation()
        ) {
            return;
        }

        $result = $this->database->safeselect(
            ['page'],
            'pages',
            'WHERE `act`=?',
            $this->database->basicvalue($action),
        );
        $page = $this->database->arow($result);

        if ($page) {
            $this->database->disposeresult($result);
            $bbCode = $this->container->get(BBCode::class);
            $pageContents = $bbCode->toHTML($page['page']);
            $this->page->append('PAGE', $pageContents);
            if ($this->request->isJSNewLocation()) {
                $this->page->JS('update', 'page', $pageContents);
            }

            return;
        }

        $this->page->location('?act=idx');
    }

    private function isBoardOffline(): bool
    {
        if (!$this->user->getPerm('can_view_board')) {
            return true;
        }

        return $this->config->getSetting('boardoffline')
        && !$this->user->getPerm('can_view_offline_board');
    }
}
