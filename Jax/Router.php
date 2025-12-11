<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;
use Jax\Models\Page as ModelsPage;
use Jax\Page\Asteroids;
use Jax\Page\Badges;
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
use Jax\Page\Solitaire;
use Jax\Page\Tardis;
use Jax\Page\Ticker;
use Jax\Page\Topic;
use Jax\Page\UCP;
use Jax\Page\UserProfile;

use function array_key_exists;
use function header;
use function preg_match;
use function str_contains;

final class Router
{
    /**
     * @var array<string,class-string>
     */
    private array $staticRoutes = [
        '' => IDX::class,
        'asteroids' => Asteroids::class,
        'badges' => Badges::class,
        'boardoffline' => BoardOffline::class,
        'buddylist' => BuddyList::class,
        'calendar' => Calendar::class,
        'download' => Download::class,
        'earthbound' => Earthbound::class,
        'idx' => IDX::class,
        'katamari' => Katamari::class,
        'members' => Members::class,
        'modcontrols' => ModControls::class,
        'post' => Post::class,
        'rainbow' => Rainbow::class,
        'search' => Search::class,
        'solitaire' => Solitaire::class,
        'tardis' => Tardis::class,
        'ticker' => Ticker::class,
        'topic' => Topic::class,
        'ucp' => UCP::class,
    ];

    /**
     * @var array<string,class-string>
     */
    private array $dynamicRoutes = [
        'logreg' => LogReg::class,
        'vf' => Forum::class,
        'vt' => Topic::class,
        'vu' => UserProfile::class,
    ];

    public function __construct(
        private readonly Request $request,
        private readonly Config $config,
        private readonly Container $container,
        private readonly Page $page,
        private readonly Session $session,
        private readonly User $user,
    ) {}

    /**
     * Redirect the user.
     */
    public function redirect(string $newLocation): void
    {
        if (!$this->request->hasCookies() && $newLocation[0] === '?') {
            $newLocation .= '&sessid=' . $this->session->get()->id;
        }

        if ($this->request->isJSAccess()) {
            $this->page->command('location', $newLocation);

            return;
        }

        header("Location: {$newLocation}");
        $this->page->append('PAGE', "Should've redirected to Location: {$newLocation}");
    }

    public function route(string $action): void
    {
        $params = [];
        if (preg_match('@(?<action>[\w]+)(?<id>(\d+))$@', $action, $match)) {
            $action = $match['action'];
            $params = ['id' => $match['id']];
        }

        $pageClassName = match (true) {
            // Board offline
            $this->isBoardOffline() && !str_contains($action, 'logreg') => BoardOffline::class,
            // Static actions
            array_key_exists($action, $this->staticRoutes) => $this->staticRoutes[$action],
            // Dynamic actions
            array_key_exists($action, $this->dynamicRoutes) => $this->dynamicRoutes[$action],
            default => null,
        };

        if ($pageClassName !== null) {
            $this->container->get($pageClassName)->route($params);

            return;
        }

        if (
            $this->request->isJSAccess()
            && !$this->request->isJSNewLocation()
        ) {
            return;
        }

        if ($this->loadCustomPage($action)) {
            return;
        }

        $this->redirect('?act=idx');
    }

    private function isBoardOffline(): bool
    {
        if (!$this->user->getGroup()?->canViewBoard) {
            return true;
        }

        return $this->config->getSetting('boardoffline')
        && !$this->user->getGroup()->canViewOfflineBoard;
    }

    /**
     * Attempts to load a custom page.
     *
     * @return bool true on success or false on failure
     */
    private function loadCustomPage(string $action): bool
    {
        $page = ModelsPage::selectOne('WHERE `act`=?', $action);

        if ($page !== null) {
            $bbCode = $this->container->get(BBCode::class);
            $pageContents = $bbCode->toHTML($page->page);
            $this->page->append('PAGE', $pageContents);
            if ($this->request->isJSNewLocation()) {
                $this->page->command('update', 'page', $pageContents);
            }

            return true;
        }

        return false;
    }
}
