<?php

declare(strict_types=1);

namespace Jax;

use Exception;

use function array_keys;
use function array_pop;
use function array_shift;
use function array_values;
use function define;
use function explode;
use function file_get_contents;
use function glob;
use function header;
use function headers_sent;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
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
use function str_replace;
use function vsprintf;

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
 * 1. The page's "components" are separated out into "meta" definitions. These are stored on `metadefs` as key/value (value being an HTML template).
 *    Template components are passed data through `vsprintf`, so each individual piece of data going into a template can be referenced using full sprintf syntax.
 *
 * 2. Theme templates can overwrite meta definitions through their board template using "<M>" tags.
 *    An example would look like this: `<M name="logo"><img src="logo.png" /></M>`
 *
 * 3. There is a "variable" syntax that templates can use:
 *    <%varname%>
 *    These variables are page-level (global) and are defined using `addvar`. They can be used in any template piece anywhere.
 *
 * 4. There is a rudimentary conditional syntax that looks like this:
 *    {if <%isguest%>=true}You should sign up!{/if}
 *    As of writing, these conditionals must use one of the following operators: =, !=, >, <, >=, <=
 *    There are also && and || which can be used for chaining conditions together.
 *
 *    Conditionals can mix and match page variables and component variables. For example: {if <%isguest%>!=true&&%1$s="Sean"}Hello Sean{/if}
 *
 *    Conditionals do not tolerate whitespace, so it must all be mashed together. To be improved one day perhaps.
 *
 * 5. The root template defines the page's widgets using this syntax:
 *    <!--NAVIGATION-->
 *    <!--PATH-->
 *
 *    These sections are defined through calls to $this->append()
 *
 *    Interestingly, I tried to create some sort of module system that would only conditionally load modules if the template has it.
 *    For example - none of the `tag_shoutbox` will be included or used if <!--SHOUTBOX--> is not in the root template.
 *    The filenames of these "modules" must be prefixed with "tag_" for this to work. Otherwise the modules will always be loaded.
 *
 * @psalm-api
 */
final class Page
{
    /**
     * @var int
     */
    public $jsaccess = 0;

    /**
     * @var bool
     */
    public $jsupdate = false;

    /**
     * @var bool
     */
    public $jsnewlocation = false;


    /**
     * @var bool
     */
    public $jsdirectlink = false;

    /**
     * @var array<string, string>
     */
    private $metadefs = [];

    /**
     * @var array<string>
     */
    private $debuginfo = [];

    /**
     * @var array<string>
     */
    private $JSOutput = [];


    private $parts = [];

    private $vars = [];

    private $userMetaDefs = [];

    private $moreFormatting = [];

    private $template;

    private $metaqueue;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly Jax $jax,
        private readonly Session $session,
    ) {
        $this->jsaccess = (int) ($_SERVER['HTTP_X_JSACCESS'] ?? 0);

        if ($this->jsaccess === 0) {
            return;
        }

        $this->jsupdate = $this->jsaccess === 1;
        $this->jsnewlocation = $this->jsaccess >= 2;
        $this->jsdirectlink = $this->jsaccess === 3;
    }

    public function get(string $part)
    {
        return $this->parts[$part];
    }

    public function append(string $part, string $content): void
    {
        $part = mb_strtoupper($part);
        if (!$this->jsaccess || $part === 'TITLE') {
            if (!isset($this->parts[$part])) {
                $this->reset($part, $content);

                return;
            }

            $this->parts[$part] .= $content;

            return;
        }
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
        if ($this->jax->c === [] && $newLocation[0] === '?') {
            $newLocation = '?sessid=' . $this->session->get('id') . '&' . mb_substr($newLocation, 1);
        }

        if ($this->jsaccess) {
            $this->JS('location', $newLocation);
        } else {
            header("Location: {$newLocation}");
        }
    }

    public function reset(string $part, string $content = ''): void
    {
        $part = mb_strtoupper($part);
        $this->parts[$part] = $content;
    }

    public function JS(...$args): void
    {
        if ($args[0] === 'softurl') {
            $this->session->erase('location');
        }

        if (!$this->jsaccess) {
            return;
        }

        $this->JSOutput[] = $args;
    }

    public function JSRaw(string $script): void
    {
        foreach (explode(PHP_EOL, $script) as $line) {
            $decoded = json_decode($line);
            if (!is_array($decoded)) {
                continue;
            }

            if (is_array($decoded[0])) {
                foreach ($decoded as $v) {
                    $this->JSOutput[] = $v;
                }
            } else {
                $this->JSOutput[] = $decoded;
            }
        }
    }

    public function JSRawArray(array $command): void
    {
        $this->JSOutput[] = $command;
    }

    public function out(): void
    {
        $this->parts['path']
            = "<div id='path' class='path'>" . $this->buildpath() . '</div>';

        if ($this->jsaccess) {
            if (!headers_sent()) {
                header('Content-type:text/plain');
            }

            foreach ($this->JSOutput as $k => $v) {
                $this->JSOutput[$k] = $this->session->addSessID($v);
            }

            echo $this->JSOutput === []
                ? ''
                : json_encode($this->JSOutput);

            return;
        }

        $autobox = ['PAGE', 'COPYRIGHT', 'USERBOX'];
        foreach ($this->parts as $part => $contents) {
            $part = mb_strtoupper((string) $part);
            if (in_array($part, $autobox)) {
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
        if ($this->checkextended($this->template, null)) {
            $this->template = $this->metaextended($this->template);
        }

        echo $this->template;
    }

    public function collapsebox(
        string $title,
        string $contents,
        $boxId = null,
    ): ?string {
        return $this->meta('collapsebox', $boxId ? ' id="' . $boxId . '"' : '', $title, $contents);
    }

    public function error($a): ?string
    {
        return $this->meta('error', $a);
    }

    public function templatehas($a): false|int
    {
        return preg_match("/<!--{$a}-->/i", (string) $this->template);
    }

    public function loadtemplate($a): void
    {
        $this->template = file_get_contents($a);
        $this->template = preg_replace_callback(
            '@<!--INCLUDE:(\w+)-->@',
            $this->includer(...),
            $this->template,
        );
        $this->template = preg_replace_callback(
            '@<M name=([\'"])([^\'"]+)\1>(.*?)</M>@s',
            $this->userMetaParse(...),
            (string) $this->template,
        );
    }

    public function loadskin($skinId): void
    {
        $skin = [];
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

        if (empty($skin)) {
            $result = $this->database->safeselect(
                ['title', 'custom', 'wrapper'],
                'skins',
                'WHERE `default`=1 LIMIT 1',
            );
            $skin = $this->database->arow($result);
            $this->database->disposeresult($result);
        }

        if (!$skin) {
            $skin = [
                'custom' => 0,
                'title' => 'Default',
                'wrapper' => false,
            ];
        }

        $t = ($skin['custom'] ? $this->domainDefinitions->getBoardPath() : '') . 'Themes/' . $skin['title'] . '/';
        $turl = ($skin['custom'] ? $this->domainDefinitions->getBoardPathUrl() : '') . 'Themes/' . $skin['title'] . '/';
        if (is_dir($t)) {
            define('THEMEPATH', $t);
            define('THEMEPATHURL', $turl);
        } else {
            define('THEMEPATH', JAXBOARDS_ROOT . '/' . $this->config->getSetting('dthemepath'));
            define('THEMEPATHURL', $this->domainDefinitions->getBoardURL() . $this->config->getSetting('dthemepath'));
        }

        define('DTHEMEPATH', JAXBOARDS_ROOT . '/' . $this->config->getSetting('dthemepath'));
        $this->loadtemplate(
            $skin['wrapper']
            ? $this->domainDefinitions->getBoardPath() . 'Wrappers/' . $skin['wrapper'] . '.html'
            : THEMEPATH . 'wrappers.html',
        );
    }

    public function userMetaParse($match): string
    {
        $this->checkextended($match[3], $match[2]);
        $this->userMetaDefs[$match[2]] = $match[3];

        return '';
    }

    public function includer(string $pageAct)
    {
        $result = $this->database->safeselect(
            'page',
            'pages',
            'WHERE `act`=?',
            $this->database->basicvalue($pageAct[1]),
        );
        $page = array_shift($this->database->arow($result));
        $this->database->disposeresult($result);

        return $page ?: '';
    }

    public function addmeta(string $meta, string $content): void
    {
        $this->metadefs[$meta] = $content;
    }

    public function loadmeta($component): void
    {
        $component = mb_strtolower((string) $component);
        $themeComponentDir = THEMEPATH . 'views/' . $component;
        if (is_dir($themeComponentDir)) {
            $componentDir = $themeComponentDir;
        } else {
            $componentDir = DTHEMEPATH . 'views/' . $component;
            if (!is_dir($componentDir)) {
                $componentDir = false;
            }
        }

        if ($componentDir === false) {
            return;
        }

        $this->metaqueue[] = $componentDir;
        $this->debug("Added {$component} to queue");
    }

    public function processqueue($process): void
    {
        while ($componentDir = array_pop($this->metaqueue)) {
            $component = pathinfo((string) $componentDir, PATHINFO_BASENAME);
            $this->debug("{$process} triggered {$component} to load");
            $meta = [];
            foreach (glob($componentDir . '/*.html') as $metaFile) {
                $metaName = pathinfo($metaFile, PATHINFO_FILENAME);
                $metaContent = file_get_contents($metaFile);
                $this->checkextended($metaContent, $metaName);
                $meta[$metaName] = $metaContent;
            }

            // Check default components for anything missing.
            $defaultComponentDir = str_replace(
                THEMEPATH,
                DTHEMEPATH,
                $componentDir,
            );
            if ($defaultComponentDir !== $componentDir) {
                foreach (glob($defaultComponentDir . '/*.html') as $metaFile) {
                    $metaName = pathinfo($metaFile, PATHINFO_FILENAME);
                    $metaContent = file_get_contents($metaFile);
                    $this->checkextended($metaContent, $metaName);
                    if (isset($meta[$metaName])) {
                        continue;
                    }

                    $meta[$metaName] = $metaContent;
                }
            }

            $this->metadefs = $meta + $this->metadefs;
        }
    }

    public function meta($meta, ...$args): string
    {
        $this->processqueue($meta);
        $formatted = vsprintf(
            str_replace(
                ['<%', '%>'],
                ['<%%', '%%>'],
                $this->userMetaDefs[$meta] ?? $this->metadefs[$meta] ?? '',
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
            return $this->metaextended($formatted);
        }

        return $formatted;
    }

    public function metaextended(string $content): string
    {
        return preg_replace_callback(
            '@{if ([^}]+)}(.*){/if}@Us',
            $this->metaextendedifcb(...),
            $this->filtervars($content),
        );
    }

    public function metaextendedifcb(array $match)
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

    public function checkextended(string $data, $meta = null): bool
    {
        if (mb_strpos($data, '{if ') !== false) {
            if (!$meta) {
                return true;
            }

            $this->moreFormatting[$meta] = true;
        }

        return false;
    }

    public function metaexists($meta): bool
    {
        return isset($this->userMetaDefs[$meta])
            || isset($this->metadefs[$meta]);
    }

    public function path(array $crumbs): bool
    {
        if (
            !isset($this->parts['path'])
            || !is_array($this->parts['path'])
        ) {
            $this->parts['path'] = [];
        }

        foreach ($crumbs as $value => $link) {
            $this->parts['path'][$link] = $value;
        }

        return true;
    }

    public function buildpath(): string
    {
        $first = true;
        $path = '';
        foreach ($this->parts['path'] as $value => $link) {
            $path .= $this->meta(
                $first
                && $this->metaexists('path-home') ? 'path-home' : 'path-part',
                $value,
                $link,
            );
            $first = false;
        }

        return $this->meta('path', $path);
    }

    public function updatepath(?array $crumbs = null): void
    {
        if ($crumbs) {
            $this->path($crumbs);
        }

        $this->JS('update', 'path', $this->buildpath());
    }

    public function debug(?string $data = null): ?string
    {
        if ($data) {
            $this->debuginfo[] = $data;

            return null;
        }

        return implode('<br>', $this->debuginfo);
    }
}
