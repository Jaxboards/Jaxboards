<?php

declare(strict_types=1);

namespace Jax;

use function array_merge;
use function explode;
use function header;
use function headers_sent;
use function implode;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mb_substr;

use const PHP_EOL;

/**
 * This class is entirely responsible for rendering the page.
 *
 * Because there weren't any good PHP template systems at the time (Blade, Twig) we built our own.
 *
 * Here's how it works:
 *
 * 1. The page's "components" are separated out into "meta" definitions. These are stored on `metaDefs` as key/value
 *    (value being an HTML template). Template components are passed data through `vsprintf`, so each individual
 *    piece of data going into a template can be referenced using full sprintf syntax.
 *
 * 2. Theme templates can overwrite meta definitions through their board template using "<M>" tags.
 *    An example would look like this: `<M name="logo"><img src="logo.png" /></M>`
 *
 * 3. There is a "variable" syntax that templates can use:
 *    <%varname%>
 *    These variables are page-level (global) and are defined using `addvar`.
 *    They can be used in any template piece anywhere.
 *
 * 4. There is a rudimentary conditional syntax that looks like this:
 *    {if <%isguest%>=true}You should sign up!{/if}
 *    As of writing, these conditionals must use one of the following operators: =, !=, >, <, >=, <=
 *    There are also && and || which can be used for chaining conditions together.
 *
 *    Conditionals can mix and match page variables and component variables.
 *    For example: {if <%isguest%>!=true&&%1$s="Sean"}Hello Sean{/if}
 *
 *    Conditionals do not tolerate whitespace, so it must all be mashed together. To be improved one day perhaps.
 *
 * 5. The root template defines the page's widgets using this syntax:
 *    <!--NAVIGATION-->
 *    <!--PATH-->
 *
 *    These sections are defined through calls to $this->append()
 *
 *    Interestingly, I tried to create some sort of module system that would only conditionally load modules
 *    if the template has it. For example - none of the `tag_shoutbox` will be included or used if <!--SHOUTBOX-->
 *    is not in the root template.
 *    The filenames of these "modules" must be prefixed with "tag_" for this to work.
 *    Otherwise the modules will always be loaded.
 */
final class Page
{
    /**
     * @var array<string>
     */
    private array $debuginfo = [];

    /**
     * These are sent when the browser requests updates through javascript.
     * They're parsed and executed in the client side javascript.
     *
     * @var array<array<mixed>>
     */
    private array $commands = [];

    /**
     * Map of human readable label to URL. Used for NAVIGATION.
     *
     * @var array<string>
     */
    private array $breadCrumbs = [];

    private string $pageTitle = '';

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly DebugLog $debugLog,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly Request $request,
        private readonly Template $template,
        private readonly Session $session,
    ) {}

    public function append(string $part, string $content): void
    {
        // When accessed through javascript, the base page is not rendered
        if ($this->request->isJSAccess()) {
            return;
        }


        $this->template->append($part, $content);
    }

    public function location(string $newLocation): void
    {
        if (!$this->request->hasCookies() && $newLocation[0] === '?') {
            $newLocation = '?sessid=' . $this->session->get('id') . '&' . mb_substr($newLocation, 1);
        }

        if ($this->request->isJSAccess()) {
            $this->command('location', $newLocation);

            return;
        }

        header("Location: {$newLocation}");
    }

    public function reset(string $part, string $content = ''): void
    {
        $this->template->reset($part, $content);
    }

    public function command(...$args): void
    {
        if ($args[0] === 'softurl') {
            $this->session->erase('location');
        }

        if (!$this->request->isJSAccess()) {
            return;
        }

        $this->commands[] = $args;
    }

    /**
     * Sometimes commands are stored in the session table's runOnce field.
     * Since they're stored as text, this expands it back out and adds it to
     * the page output.
     */
    public function commandsFromString(string $script): void
    {
        foreach (explode(PHP_EOL, $script) as $line) {
            $decoded = json_decode($line);
            if (!is_array($decoded)) {
                continue;
            }

            if (is_array($decoded[0])) {
                $this->commands = array_merge($this->commands, $decoded);

                continue;
            }

            $this->commands[] = $decoded;
        }
    }

    public function out(): void
    {
        if ($this->request->isJSAccess()) {
            $this->outputJavascriptCommands();

            return;
        }

        $this->append('PATH', $this->buildPath());
        $this->append('TITLE', $this->getPageTitle());

        echo $this->session->addSessId($this->template->render());
    }

    public function collapseBox(
        string $title,
        string $contents,
        ?string $boxId = null,
    ): ?string {
        return $this->template->meta('collapsebox', $boxId ? " id=\"{$boxId}\"" : '', $title, $contents);
    }

    public function error(string $error): ?string
    {
        return $this->template->meta('error', $error);
    }

    public function loadSkin(?int $skinId): void
    {
        $skin = null;
        if ($skinId) {
            $result = $this->database->safeselect(
                ['title', 'custom', 'wrapper'],
                'skins',
                'WHERE id=? LIMIT 1',
                $skinId,
            );
            $skin = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        // Couldn't find custom skin, get the default
        if ($skin === null) {
            $result = $this->database->safeselect(
                ['title', 'custom', 'wrapper'],
                'skins',
                'WHERE `default`=1 LIMIT 1',
            );
            $skin = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        // We've exhausted all other ways of finding the right skin
        // Fallback to default
        if ($skin === null) {
            $skin = [
                'custom' => 0,
                'title' => 'Default',
                'wrapper' => false,
            ];
        }

        $themePath = ($skin['custom'] ? $this->domainDefinitions->getBoardPath() : '') . '/Themes/' . $skin['title'];
        $themePathUrl = ($skin['custom'] ? $this->domainDefinitions->getBoardPathUrl() : '') . '/Themes/' . $skin['title'];

        // Custom theme found but files not there, also fallback to default
        if (!is_dir($themePath)) {
            $themePath = $this->domainDefinitions->getDefaultThemePath();
            $themePathUrl = $this->domainDefinitions->getBoardURL() . '/' . $this->config->getSetting('dthemepath');
        }

        $this->template->setThemePath($themePath);

        // Load CSS
        $this->append(
            'CSS',
            '<link rel="stylesheet" type="text/css" href="' . $themePathUrl . '/css.css">'
            . '<link rel="preload" as="style" type="text/css" href="./Service/wysiwyg.css" onload="this.onload=null;this.rel=\'stylesheet\'" />',
        );

        // Load Wrapper
        $this->template->load(
            $skin['wrapper']
            ? $this->domainDefinitions->getBoardPath() . '/Wrappers/' . $skin['wrapper'] . '.html'
            : $themePath . '/wrappers.html',
        );
    }

    public function path(array $crumbs): void
    {
        $this->breadCrumbs = array_merge($this->breadCrumbs, $crumbs);
    }

    public function setPageTitle(string $title): void
    {
        $this->pageTitle = $title;
        $this->template->reset('TITLE', $this->getPageTitle());
    }

    public function getPageTitle()
    {
        return (
            $this->template->meta('title')
            ?: $this->config->getSetting('boardname')
            ?: 'JaxBoards'
        ) . ($this->pageTitle ? ' -> ' . $this->pageTitle : '');
    }

    public function debug(?string $data = null): ?string
    {
        if ($data) {
            $this->debuginfo[] = $data;

            return null;
        }

        return implode('<br>', $this->debuginfo);
    }

    private function outputJavascriptCommands(): void
    {
        if (!headers_sent()) {
            header('Content-type:application/json');
        }

        // Update browser title and breadcrumbs when changing location
        if ($this->request->isJSNewLocation()) {
            $this->command('title', $this->getPageTitle());
            $this->command('update', 'path', $this->buildpath());
        }

        echo json_encode($this->commands);
    }

    private function buildPath(): string
    {
        $first = true;
        $path = '';
        foreach ($this->breadCrumbs as $value => $link) {
            $path .= $this->template->meta(
                $first
                && $this->template->metaExists('path-home') ? 'path-home' : 'path-part',
                $link,
                $value,
            );
            $first = false;
        }

        return $this->template->meta('path', $path);
    }
}
