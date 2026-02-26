<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;

use function array_key_exists;
use function header;
use function http_build_query;
use function ksort;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;

final class Router
{
    /**
     * Keys: names of routes
     * Values: paths for URLs with params.
     *
     * @var array<string,string>
     */
    private array $urls = [];

    /**
     * Map of paths to class-string.
     *
     * @var array<string,string>
     */
    private array $paths = [];

    public function __construct(
        private readonly Request $request,
        private readonly Container $container,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly Session $session,
    ) {}

    /**
     * Redirect to a new URL.
     *
     * @param array<string,null|int|string> $params
     */
    public function redirect(string $newLocation, array $params = [], ?string $hash = null): void
    {
        $newLocation = ($this->url($newLocation, $params) ?: $newLocation) . ($hash ?? '');

        if (!$this->request->hasCookies() && $newLocation[0] === '/') {
            $newLocation .= '&sessid=' . $this->session->get()->id;
        }

        // Avoid circular dependency
        $page = $this->container->get(Page::class);

        if ($this->request->isJSAccess()) {
            $page->command('preventNavigation');
            $page->command('location', $newLocation);

            return;
        }

        header("Location: {$newLocation}");
        $page->append('PAGE', "Should've redirected to Location: {$newLocation}");
    }

    /**
     * Figures out where to go!
     *
     * @return bool True if route found, false if not
     */
    public function route(string $path = ''): bool
    {
        if ($path === '' || $path[0] !== '/') {
            $path = "/{$path}";
        }

        foreach ($this->paths as $regex => $className) {
            if (!preg_match($regex, $path, $match)) {
                continue;
            }

            $this->container->get($className)->route($match);

            return true;
        }

        return false;
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
        // make sure query param order is consistent
        ksort($params);

        if (array_key_exists($name, $this->urls)) {
            $foundParams = [];

            // Replaces {param} values with their values from $params
            $path = preg_replace_callback(
                '/\/\{(\w+)\}/',
                static function ($match) use ($params, &$foundParams): string {
                    [, $name] = $match;

                    $foundParams[] = $name;

                    return array_key_exists($name, $params) ? "/{$params[$name]}" : '';
                },
                $this->urls[$name],
            );

            // Anything not a path param gets added on as a query parameter
            $queryParams = Lodash::without($params, $foundParams);

            return $path . ($queryParams !== [] ? '?' . http_build_query($queryParams) : '');
        }

        // These are aliases
        return (
            match ($name) {
                'shoutbox' => '?module=shoutbox',
                'inbox' => '/ucp/inbox',
                'acp' => '/ACP/',
                default => '',
            } . ($params !== [] ? '&' . http_build_query($params) : '')
        );
    }

    /**
     * Returns the root URL of the application.
     */
    public function getRootURL(): string
    {
        return $this->domainDefinitions->getBoardURL();
    }

    /**
     * Add a new potential route.
     */
    public function get(string $name, string $path, string $classString): void
    {
        $this->urls[$name] = $path;

        // Replaces {param} with a name-captured subgroup (?<param>.*) and makes a full regex
        $regexedPath = '@^' . preg_replace('/\/\{(\w+)\}/', '(?:\/(?<$1>[^/]+))?', $path) . '$@';
        $this->paths[$regexedPath] = $classString;
    }
}
