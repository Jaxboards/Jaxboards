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
use function http_build_query;
use function in_array;
use function mb_strtolower;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function str_contains;

final class Router
{
    /**
     * @var array<string,class-string>
     */
    private array $staticRoutes = [];

    /**
     * Keys: names of routes
     * Values: paths for URLs with params.
     *
     * @var array<string,string>
     */
    private array $urls = [];
    private array $paths = [];

    public function __construct(
        private readonly Request $request,
        private readonly Config $config,
        private readonly Container $container,
        private readonly Page $page,
        private readonly Session $session,
        private readonly User $user,
    ) {
        $this->get('', '/', IDX::class);
        $this->get('asteroids', '/asteroids', Asteroids::class);
        $this->get('badges', '/badges', Badges::class);
        $this->get('boardoffline', '/boardoffline', BoardOffline::class);
        $this->get('buddylist', '/buddylist', BuddyList::class);
        $this->get('calendar', '/calendar', Calendar::class);
        $this->get('download', '/download', Download::class);
        $this->get('earthbound', '/earthbound', Earthbound::class);
        $this->get('index', '/', IDX::class);
        $this->get('katamari', '/katamari', Katamari::class);
        $this->get('members', '/members', Members::class);
        $this->get('modcontrols', '/modcontrols', ModControls::class);
        $this->get('forum', '/forum/{id}/{slug}', Forum::class);
        $this->get('post', '/post', Post::class);
        $this->get('profile', '/profile/{id}/{page}', UserProfile::class);
        $this->get('rainbow', '/rainbow', Rainbow::class);
        $this->get('search', '/search', Search::class);
        $this->get('solitaire', '/solitaire', Solitaire::class);
        $this->get('tardis', '/tardis', Tardis::class);
        $this->get('ticker', '/ticker', Ticker::class);
        $this->get('topic', '/topic/{id}/{slug}', Topic::class);
        $this->get('ucp', '/ucp', UCP::class);

        $this->get('register', '/register', LogReg::class);
        $this->get('logout', '/logout', LogReg::class);
        $this->get('login', '/login', LogReg::class);
        $this->get('toggleInvisible', '/toggleInvisible', LogReg::class);
        $this->get('forgotPassword', '/forgotPassword', LogReg::class);
    }

    /**
     * Redirect to a new URL.
     *
     * @param array<string,null|int|string> $params
     */
    public function redirect(
        string $newLocation,
        array $params = [],
        ?string $hash = null,
    ): void {
        $newLocation = ($this->url($newLocation, $params) ?: $newLocation) . ($hash ?? '');

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

    public function route(): void
    {
        $action = mb_strtolower($this->request->asString->both('act') ?? '');
        $path = $this->request->asString->both('path') ?? '';

        if ($path !== '' && $action === '') {
            $this->routeByPath($path);

            return;
        }

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

        $this->redirect('index');
    }

    /**
     * This function will generate a URL for a given named route.
     * Ex:
     * path: /topic/{id}
     * params: ['id' => 1, 'getlast' => 1] becomes:
     * returns: /topic/1?getlast=1.
     *
     * @param array<string,null|int|string> $params
     */
    public function url(string $name, array $params = []): string
    {
        if (array_key_exists($name, $this->urls)) {
            $foundParams = [];

            // Replaces {param} values with their values from $params
            $path = preg_replace_callback(
                '/\/\{(\w+)\}/',
                static function ($match) use ($params, &$foundParams): string {
                    [, $name] = $match;

                    $foundParams[] = $name;

                    return array_key_exists($name, $params)
                        ? "/{$params[$name]}"
                        : '';
                },
                $this->urls[$name],
            );

            // Anything not a path param gets added on as a query parameter
            $queryParams = $this->without($params, $foundParams);

            return $path . ($queryParams !== [] ? '?' . http_build_query($queryParams) : '');
        }

        // These are aliases and will be removed soon
        return match ($name) {
            'category' => "?act=vc{$params['id']}",
            'shoutbox' => '?module=shoutbox',
            default => '',
        };
    }

    private function get(string $name, string $path, string $classString): void
    {
        $this->staticRoutes[$name] = $classString;
        $this->urls[$name] = $path;

        // Replaces {param} with a name-captured subgroup (?<param>.*) and makes a full regex
        $regexedPath = '@^' . preg_replace('/\/\{(\w+)\}/', '(?:\/(?<$1>[^/]+))?', $path) . '$@';
        $this->paths[$regexedPath] = $classString;
    }

    /**
     * Routes by `path` param (generated by .htaccess).
     */
    private function routeByPath(string $path): void
    {
        if ($path[0] !== '/') {
            $path = "/{$path}";
        }

        foreach ($this->paths as $regex => $className) {
            if (preg_match($regex, $path, $match)) {
                $this->container->get($className)->route($match);

                return;
            }
        }

        $this->redirect('index');
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

    /**
     * Returns a newly constructed array without $keys.
     *
     * @param array<string,mixed> $array
     * @param array<string>       $keys
     *
     * @return array<string,mixed>
     */
    private function without(array $array, array $keys): array
    {
        $newArray = [];
        foreach ($array as $key => $value) {
            if (in_array($key, $keys, true)) {
                continue;
            }

            $newArray[$key] = $value;
        }

        return $newArray;
    }
}
