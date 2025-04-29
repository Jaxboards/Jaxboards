<?php

declare(strict_types=1);

namespace Jax;

use Exception;

use function array_keys;
use function array_merge;
use function array_pop;
use function array_values;
use function explode;
use function file_get_contents;
use function glob;
use function header;
use function headers_sent;
use function htmlspecialchars_decode;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_string;
use function json_decode;
use function json_encode;
use function mb_strpos;
use function mb_strtolower;
use function mb_strtoupper;
use function mb_substr;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function str_contains;
use function str_replace;
use function vsprintf;

use const ENT_QUOTES;
use const PATHINFO_BASENAME;
use const PATHINFO_FILENAME;
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
     * @var array<string, string>
     */
    private array $metaDefs = [];

    /**
     * @var array<string>
     */
    private array $debuginfo = [];

    /**
     * @var array<array<mixed>>
     */
    private array $javascriptCommands = [];


    /**
     * @var array<string,string>
     */
    private array $parts = [];

    /**
     * @var array<string,string>
     */
    private array $vars = [];

    /**
     * @var array<string,string>
     */
    private array $userMetaDefs = [];

    /**
     * @var array<string,bool>
     */
    private array $moreFormatting = [];

    /**
     * Map of human readable label to URL. Used for NAVIGATION.
     *
     * @var array<string>
     */
    private array $breadCrumbs = [];

    private string $template;

    /**
     * @var array<string>
     */
    private array $metaqueue;

    private ?string $themePath = null;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly Request $request,
        private readonly Session $session,
    ) {}

    public function append(string $part, string $content): void
    {
        // When accessed through javascript, the base page is not rendered
        // so we can skip all parts except the title
        if ($this->request->isJSAccess() && $part !== 'TITLE') {
            return;
        }

        if (!isset($this->parts[$part])) {
            $this->reset($part, $content);

            return;
        }

        $this->parts[$part] .= $content;
    }

    public function addvar(string $varName, string $value): void
    {
        $this->vars['<%' . $varName . '%>'] = $value;
    }

    public function filtervars(string $string): string
    {
        return str_replace(array_keys($this->vars), array_values($this->vars), $string);
    }

    public function location(string $newLocation): void
    {
        if (!$this->request->hasCookies() && $newLocation[0] === '?') {
            $newLocation = '?sessid=' . $this->session->get('id') . '&' . mb_substr($newLocation, 1);
        }

        if ($this->request->isJSAccess()) {
            $this->JS('location', $newLocation);

            return;
        }

        header("Location: {$newLocation}");
    }

    public function reset(string $part, string $content = ''): void
    {
        $this->parts[$part] = $content;
    }

    public function JS(...$args): void
    {
        if ($args[0] === 'softurl') {
            $this->session->erase('location');
        }

        if (!$this->request->isJSAccess()) {
            return;
        }

        $this->javascriptCommands[] = $args;
    }

    /**
     * Sometimes commands are stored in the session table's runOnce field.
     * Since they're stored as text, this expands it back out and adds it to
     * the page output.
     */
    public function JSRaw(string $script): void
    {
        foreach (explode(PHP_EOL, $script) as $line) {
            $decoded = json_decode($line);
            if (!is_array($decoded)) {
                continue;
            }

            if (is_array($decoded[0])) {
                $this->javascriptCommands = array_merge($this->javascriptCommands, $decoded);

                continue;
            }

            $this->javascriptCommands[] = $decoded;
        }
    }

    public function JSRawArray(array $command): void
    {
        $this->javascriptCommands[] = $command;
    }

    public function out(): void
    {
        $this->parts['path']
            = "<div id='path' class='path'>" . $this->buildPath() . '</div>';

        if ($this->request->isJSAccess()) {
            $this->outputJavascriptCommands();

            return;
        }

        $autoBox = ['PAGE', 'COPYRIGHT', 'USERBOX'];
        foreach ($this->parts as $part => $contents) {
            $part = mb_strtoupper((string) $part);
            if (in_array($part, $autoBox)) {
                $contents = '<div id="' . mb_strtolower($part) . '">' . $contents . '</div>';
            }

            if ($part === 'PATH') {
                $this->template
                    = preg_replace('@<!--PATH-->@', (string) $contents, (string) $this->template, 1);
            }

            $this->template = str_replace('<!--' . $part . '-->', $contents, $this->template);
        }

        $this->template = $this->filtervars($this->template);
        $this->template = $this->session->addSessId($this->template);
        if ($this->checkExtended($this->template, null)) {
            $this->template = $this->metaExtended($this->template);
        }

        echo $this->template;
    }

    public function collapseBox(
        string $title,
        string $contents,
        ?string $boxId = null,
    ): ?string {
        return $this->meta('collapsebox', $boxId ? " id=\"{$boxId}\"" : '', $title, $contents);
    }

    public function error(string $error): ?string
    {
        return $this->meta('error', $error);
    }

    public function templateHas(string $part): false|int
    {
        return preg_match("/<!--{$part}-->/i", (string) $this->template);
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

        $this->themePath = ($skin['custom'] ? $this->domainDefinitions->getBoardPath() : '') . '/Themes/' . $skin['title'];
        $themePathUrl = ($skin['custom'] ? $this->domainDefinitions->getBoardPathUrl() : '') . '/Themes/' . $skin['title'];

        // Custom theme found but files not there, also fallback to default
        if (!is_dir($this->themePath)) {
            $this->themePath = $this->domainDefinitions->getDefaultThemePath();
            $themePathUrl = $this->domainDefinitions->getBoardURL() . '/' . $this->config->getSetting('dthemepath');
        }

        // Load CSS
        $this->append(
            'CSS',
            '<link rel="stylesheet" type="text/css" href="' . $themePathUrl . '/css.css">'
            . '<link rel="preload" as="style" type="text/css" href="./Service/wysiwyg.css" onload="this.onload=null;this.rel=\'stylesheet\'" />',
        );

        // Load Wrapper
        $this->loadTemplate(
            $skin['wrapper']
            ? $this->domainDefinitions->getBoardPath() . '/Wrappers/' . $skin['wrapper'] . '.html'
            : $this->themePath . '/wrappers.html',
        );
    }

    public function addMeta(string $meta, string $content): void
    {
        $this->metaDefs[$meta] = $content;
    }

    public function loadMeta(string $component): void
    {
        $component = mb_strtolower($component);
        $themeComponentDir = $this->themePath . '/views/' . $component;
        $defaultThemeComponentDir = $this->domainDefinitions->getDefaultThemePath() . '/views/' . $component;

        $componentDir = match (true) {
            is_dir($themeComponentDir) => $themeComponentDir,
            is_dir($defaultThemeComponentDir) => $defaultThemeComponentDir,
            default => null,
        };

        if ($componentDir === null) {
            return;
        }

        $this->metaqueue[] = $componentDir;
        $this->debug("Added {$component} to queue");
    }

    public function meta(string $meta, ...$args): string
    {
        $this->processQueue($meta);
        $formatted = vsprintf(
            str_replace(
                ['<%', '%>'],
                ['<%%', '%%>'],
                $this->userMetaDefs[$meta] ?? $this->metaDefs[$meta] ?? '',
            ),
            isset($args[0]) && is_array($args[0]) ? $args[0] : $args,
        );
        if ($formatted === false) {
            throw new Exception($meta . ' has too many arguments');
        }

        if (
            isset($this->moreFormatting[$meta])
            && $this->moreFormatting[$meta]
        ) {
            return $this->metaExtended($formatted);
        }

        return $formatted;
    }

    public function metaExists(string $meta): bool
    {
        return isset($this->userMetaDefs[$meta])
            || isset($this->metaDefs[$meta]);
    }

    public function path(array $crumbs): void
    {
        $this->breadCrumbs = array_merge($this->breadCrumbs, $crumbs);
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
            $this->JS('title', htmlspecialchars_decode((string) $this->parts['TITLE'], ENT_QUOTES));
            $this->JS('update', 'path', $this->buildpath());
        }

        echo json_encode($this->javascriptCommands);
    }

    private function loadTemplate(string $file): void
    {
        $this->template = file_get_contents($file);
        $this->template = preg_replace_callback(
            '@<M name=([\'"])([^\'"]+)\1>(.*?)</M>@s',
            $this->userMetaParse(...),
            (string) $this->template,
        );
    }

    private function userMetaParse(array $match): string
    {
        $this->checkExtended($match[3], $match[2]);
        $this->userMetaDefs[$match[2]] = $match[3];

        return '';
    }

    private function processQueue(string $process): void
    {
        while ($componentDir = array_pop($this->metaqueue)) {
            $component = pathinfo((string) $componentDir, PATHINFO_BASENAME);
            $this->debug("{$process} triggered {$component} to load");
            $meta = [];
            foreach (glob($componentDir . '/*.html') as $metaFile) {
                $metaName = (string) pathinfo($metaFile, PATHINFO_FILENAME);
                $metaContent = file_get_contents($metaFile);
                if (!$metaContent) {
                    continue;
                }
                $this->checkExtended($metaContent, $metaName);
                $meta[$metaName] = $metaContent;
            }

            // Check default components for anything missing.
            $defaultComponentDir = str_replace(
                $this->themePath,
                $this->domainDefinitions->getDefaultThemePath(),
                $componentDir,
            );
            if ($defaultComponentDir !== $componentDir) {
                foreach (glob($defaultComponentDir . '/*.html') as $metaFile) {
                    $metaName = (string) pathinfo($metaFile, PATHINFO_FILENAME);
                    $metaContent = file_get_contents($metaFile);
                    $this->checkExtended($metaContent, $metaName);
                    if (isset($meta[$metaName]) || !is_string($metaContent)) {
                        continue;
                    }

                    $meta[$metaName] = $metaContent;
                }
            }

            $this->metaDefs = array_merge($meta, $this->metaDefs);
        }
    }

    private function metaExtended(string $content): string
    {
        return (string) preg_replace_callback(
            '@{if ([^}]+)}(.*){/if}@Us',
            $this->metaExtendedIfCB(...),
            $this->filtervars($content),
        );
    }

    private function metaExtendedIfCB(array $match)
    {
        $logicalOperator = mb_strpos((string) $match[1], '||') !== false
            ? '||'
            : '&&';

        foreach (explode($logicalOperator, (string) $match[1]) as $piece) {
            preg_match('@(\S+?)\s*([!><]?=|[><])\s*(\S*)@', $piece, $args);

            $conditionPasses = match ($args[2]) {
                '=' => $args[1] === $args[3],
                '!=' => $args[1] !== $args[3],
                '>=' => $args[1] >= $args[3],
                '>' => $args[1] > $args[3],
                '<=' => $args[1] <= $args[3],
                '<' => $args[1] < $args[3],
                default => false,
            };

            if ($logicalOperator === '&&' && !$conditionPasses) {
                break;
            }

            if ($logicalOperator === '||' && $conditionPasses) {
                break;
            }
        }

        return $conditionPasses ? $match[2] : '';
    }

    private function checkExtended(string $data, ?string $meta = null): bool
    {
        if (str_contains($data, '{if ')) {
            if (!$meta) {
                return true;
            }

            $this->moreFormatting[$meta] = true;
        }

        return false;
    }

    private function buildPath(): string
    {
        $first = true;
        $path = '';
        foreach ($this->breadCrumbs as $value => $link) {
            $path .= $this->meta(
                $first
                && $this->metaExists('path-home') ? 'path-home' : 'path-part',
                $link,
                $value,
            );
            $first = false;
        }

        return $this->meta('path', $path);
    }
}
