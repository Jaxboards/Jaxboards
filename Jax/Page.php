<?php

declare(strict_types=1);

namespace Jax;

use Error;
use Exception;
use Jax\Models\Skin;

use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function error_log;
use function explode;
use function header;
use function headers_sent;
use function is_array;
use function json_decode;
use function json_encode;
use function trim;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

final class Page
{
    /**
     * @var array<array<mixed>> JS commands
     */
    private array $commands = [];

    /**
     * Map of human readable label to URL. Used for NAVIGATION.
     *
     * @var array<string,string>
     */
    private array $breadCrumbs = [];

    /**
     * Open graph data for the current page (used by embeds).
     *
     * @var array <string, string>
     */
    private array $openGraphData = [];

    private string $pageTitle = '';

    private ?string $earlyFlush = null;

    public function __construct(
        private readonly Config $config,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly FileSystem $fileSystem,
        private readonly Request $request,
        private readonly Router $router,
        private readonly Session $session,
        private readonly Template $template,
        private readonly User $user,
    ) {}

    public function append(string $part, string $content): void
    {
        // When accessed through javascript, the base page is not rendered
        if ($this->request->isJSAccess()) {
            return;
        }

        $this->template->append($part, $content);
    }

    /**
     * @param mixed $args
     */
    public function command(...$args): void
    {
        if ($args[0] === 'preventNavigation') {
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
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($line === '0') {
                continue;
            }

            try {
                $decoded = json_decode($line, flags: JSON_THROW_ON_ERROR);
            } catch (Exception) {
                error_log('Invalid JSON in session: ' . $script);

                continue;
            }

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

    /**
     * Outputs $content and ensures nothing else is printed afterwards.
     */
    public function earlyFlush(string $content): void
    {
        $this->earlyFlush = $content;
    }

    public function out(): string
    {
        if ($this->request->isJSAccess()) {
            return $this->outputJavascriptCommands();
        }

        if ($this->earlyFlush) {
            return $this->earlyFlush;
        }

        $this->append('PATH', $this->buildPath());
        $this->append('TITLE', $this->getPageTitle());

        foreach ($this->openGraphData as $key => $value) {
            $this->append('OPENGRAPH', <<<HTML
                <meta property="og:{$key}" content="{$value}">
                HTML);
        }

        return $this->session->addSessId($this->template->out());
    }

    public function collapseBox(string $title, string $contents, string $boxID): string
    {
        return $this->template->render('global/collapsebox', [
            'boxID' => $boxID,
            'title' => $title,
            'content' => $contents,
        ]);
    }

    public function error(string $error): string
    {
        return $this->template->render('error', ['message' => $error]);
    }

    public function loadSkin(): void
    {
        $skinId = $this->user->get()->skinID;

        $skin = $this->getSelectedSkin($skinId);

        if (!$skin instanceof Skin) {
            throw new Error('Unable to get any available board skin');
        }

        $themePath = $this->fileSystem->pathJoin(
            $skin->custom !== 0 ? $this->domainDefinitions->getBoardPath() : 'Service',
            'Themes',
            $skin->title,
        );
        $cssFile = $this->fileSystem->pathJoin($themePath, 'css.css');
        $themeUrl = ($skin->custom !== 0 ? $this->domainDefinitions->getBoardURL() : '') . '/' . $cssFile;

        // Custom theme found but files not there, also fallback to default
        if (!$this->fileSystem->getFileInfo($cssFile)->isFile()) {
            $themePath = $this->domainDefinitions->getDefaultThemePath();
            $cssFile = $this->fileSystem->pathJoin($themePath, 'css.css');
            $themeUrl = $this->router->getRootURL() . '/' . $cssFile;
        }

        $this->template->setThemePath($themePath);

        // Add cache busting
        $themeUrl .= '?' . $this->fileSystem->getFileInfo($cssFile)->getMTime();

        $globalCSS = '/Service/Themes/global.css';
        $globalCSSMtime = $this->fileSystem->getFileInfo($globalCSS)->getMTime();

        // Load CSS
        $this->append('CSS', <<<HTML
                <link
                    rel="preload"
                    as="style"
                    type="text/css"
                    href="{$globalCSS}?{$globalCSSMtime}"
                    onload="this.onload=null;this.rel='stylesheet'"
                >
                <link rel="stylesheet" type="text/css" href="{$themeUrl}">
            HTML);

        // Load Wrapper
        $skinWrapper = $skin->wrapper !== ''
            ? $this->domainDefinitions->getBoardPath() . '/Wrappers/' . $skin->wrapper . '.html'
            : '';
        $this->template->load(
            $skinWrapper && $this->fileSystem->getFileInfo($skinWrapper)->isFile()
                ? $skinWrapper
                : $this->domainDefinitions->getDefaultThemePath() . '/wrapper.html',
        );
    }

    /**
     * @param array<string,string> $crumbs Map of URLs to human readable labels. Used for NAVIGATION.
     */
    public function setBreadCrumbs(array $crumbs): void
    {
        $this->breadCrumbs = array_merge($this->breadCrumbs, $crumbs);
    }

    public function setPageTitle(string $title): void
    {
        $this->pageTitle = $title;
    }

    /**
     * Sets open graph data. Drops empty values.
     *
     * @param array<string,?string> $data
     */
    public function setOpenGraphData(array $data): void
    {
        $this->openGraphData = array_merge(
            $this->openGraphData,
            array_filter($data, static fn(?string $value): bool => (bool) $value),
        );
    }

    /**
     * Gets the user selected skin.
     * If not available, gets the board default.
     * If THAT isn't available, get jaxboards default skin.
     */
    public function getSelectedSkin(?int $skinId): ?Skin
    {
        $skin = $skinId ? Skin::selectOne($skinId) : null;

        // Couldn't find custom skin, get the default
        $skin ??= Skin::selectOne('WHERE `default`=? LIMIT 1', 1);

        return $skin;
    }

    private function getPageTitle(): string
    {
        return (
            ($this->config->getSetting('boardname') ?: 'JaxBoards')
            . ($this->pageTitle !== '' ? ' -> ' . $this->pageTitle : '')
        );
    }

    private function outputJavascriptCommands(): string
    {
        if (!headers_sent()) {
            header('Content-type:application/json');
        }

        // Update browser title and breadcrumbs when changing location
        if ($this->request->isJSNewLocation()) {
            $this->command('title', $this->getPageTitle());
            $this->command('update', 'path', $this->buildpath());
        }

        return json_encode($this->commands, JSON_THROW_ON_ERROR);
    }

    private function buildPath(): string
    {
        return $this->template->render('global/breadcrumbs', [
            'crumbs' => array_map(
                static fn($url, $text): array => [
                    'url' => $url,
                    'text' => $text,
                ],
                array_keys($this->breadCrumbs),
                array_values($this->breadCrumbs),
            ),
        ]);
    }
}
