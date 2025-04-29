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

final class Page
{
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
        $skin = $this->getSelectedSkin($skinId);

        $themePath = ($skin['custom'] ? $this->domainDefinitions->getBoardPath() : '') . '/Themes/' . $skin['title'];
        $themeUrl = ($skin['custom'] ? $this->domainDefinitions->getBoardPathUrl() : '') . '/Themes/' . $skin['title'];

        // Custom theme found but files not there, also fallback to default
        if (!is_dir($themePath)) {
            $themePath = $this->domainDefinitions->getDefaultThemePath();
            $themeUrl = $this->domainDefinitions->getBoardURL() . '/' . $this->config->getSetting('dthemepath');
        }

        $this->template->setThemePath($themePath);

        // Load CSS
        $this->append(
            'CSS',
            <<<"HTML"
                <link rel="stylesheet" type="text/css" href="{$themeUrl}/css.css">
                <link rel="preload" as="style" type="text/css" href="./Service/wysiwyg.css" onload="this.onload=null;this.rel=\'stylesheet\'" />
            HTML
        );

        // Load Wrapper
        $this->template->load(
            $skin['wrapper']
            ? $this->domainDefinitions->getBoardPath() . '/Wrappers/' . $skin['wrapper'] . '.html'
            : $themePath . '/wrappers.html',
        );
    }

    /**
     * Gets the user selected skin.
     * If not available, gets the board default.
     * If THAT isn't available, get jaxboards default skin.
     * @return array<string,mixed>
     */
    private function getSelectedSkin(?int $skinId): array
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

        return $skin;
    }

    public function setBreadCrumbs(array $crumbs): void
    {
        $this->breadCrumbs = array_merge($this->breadCrumbs, $crumbs);
    }

    public function setPageTitle(string $title): void
    {
        $this->pageTitle = $title;
        $this->template->reset('TITLE', $this->getPageTitle());
    }

    private function getPageTitle(): string
    {
        return (
            $this->template->meta('title')
            ?: $this->config->getSetting('boardname')
            ?: 'JaxBoards'
        ) . ($this->pageTitle ? ' -> ' . $this->pageTitle : '');
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
