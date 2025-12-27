<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;
use Jax\Routes\API;
use Jax\Routes\Asteroids;
use Jax\Routes\Badges;
use Jax\Routes\BoardIndex;
use Jax\Routes\BoardOffline;
use Jax\Routes\BuddyList;
use Jax\Routes\Calendar;
use Jax\Routes\CustomPage;
use Jax\Routes\Download;
use Jax\Routes\Earthbound;
use Jax\Routes\Forum;
use Jax\Routes\Katamari;
use Jax\Routes\LogReg;
use Jax\Routes\Members;
use Jax\Routes\ModControls;
use Jax\Routes\Post;
use Jax\Routes\Rainbow;
use Jax\Routes\Search;
use Jax\Routes\Solitaire;
use Jax\Routes\Tardis;
use Jax\Routes\Ticker;
use Jax\Routes\Topic;
use Jax\Routes\UCP;
use Jax\Routes\UserProfile;

use function array_key_exists;
use function header;
use function http_build_query;
use function in_array;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function str_contains;
use function trim;

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
        $this->get('', '/', BoardIndex::class);
        $this->get('api', '/api/{method}', API::class);
        $this->get('asteroids', '/asteroids', Asteroids::class);
        $this->get('badges', '/badges', Badges::class);
        $this->get('buddylist', '/buddylist', BuddyList::class);
        $this->get('calendar', '/calendar', Calendar::class);
        $this->get('category', '/', BoardIndex::class);
        $this->get('download', '/download', Download::class);
        $this->get('earthbound', '/earthbound', Earthbound::class);
        $this->get('index', '/', BoardIndex::class);
        $this->get('katamari', '/katamari', Katamari::class);
        $this->get('members', '/members', Members::class);
        $this->get('modcontrols', '/modcontrols/{do}', ModControls::class);
        $this->get('forum', '/forum/{id}/{slug}', Forum::class);
        $this->get('post', '/post', Post::class);
        $this->get('profile', '/profile/{id}/{page}', UserProfile::class);
        $this->get('rainbow', '/rainbow', Rainbow::class);
        $this->get('search', '/search', Search::class);
        $this->get('solitaire', '/solitaire', Solitaire::class);
        $this->get('tardis', '/tardis', Tardis::class);
        $this->get('ticker', '/ticker', Ticker::class);
        $this->get('topic', '/topic/{id}/{slug}', Topic::class);
        $this->get('ucp', '/ucp/{what}', UCP::class);

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

        if (!$this->request->hasCookies() && $newLocation[0] === '/') {
            $newLocation .= '&sessid=' . $this->session->get()->id;
        }

        if ($this->request->isJSAccess()) {
            $this->page->command('location', $newLocation);

            return;
        }

        header("Location: {$newLocation}");
        $this->page->append('PAGE', "Should've redirected to Location: {$newLocation}");
    }

    /**
     * Figures out where to go!
     *
     * @return bool True if route found, false if not
     */
    public function route(string $path): bool
    {
        if ($this->isBoardOffline() && !str_contains($path, 'login')) {
            $this->container->get(BoardOffline::class)->route([]);

            return true;
        }

        if ($this->routeByPath($path)) {
            return true;
        }

        return (bool) $this->container->get(CustomPage::class)->route(trim($path, '/'));
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

        // These are aliases
        return match ($name) {
            'shoutbox' => '?module=shoutbox',
            'inbox' => '/ucp/inbox',
            'acp' => '/ACP/',
            default => '',
        } . ($params !== [] ? '&' . http_build_query($params) : '');
    }

    /**
     * Add a new potential route.
     */
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
    private function routeByPath(string $path = ''): bool
    {
        if ($path === '' || $path[0] !== '/') {
            $path = "/{$path}";
        }

        foreach ($this->paths as $regex => $className) {
            if (preg_match($regex, $path, $match)) {
                $this->container->get($className)->route($match);

                return true;
            }
        }

        return false;
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
