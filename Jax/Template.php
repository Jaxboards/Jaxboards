<?php

declare(strict_types=1);

namespace Jax;

use InvalidArgumentException;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_pop;
use function array_reduce;
use function array_values;
use function explode;
use function file_get_contents;
use function glob;
use function in_array;
use function is_array;
use function is_dir;
use function is_string;
use function mb_strtolower;
use function pathinfo;
use function preg_match;
use function preg_replace_callback;
use function str_contains;
use function str_replace;
use function vsprintf;

use const PATHINFO_BASENAME;
use const PATHINFO_FILENAME;

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
final class Template
{
    /**
     * @var array<string, string>
     */
    private array $metaDefs = [];

    /**
     * Array of component directories to load.
     * Any component directory added to the queue will have all of its HTML files loaded.
     *
     * @var array<string>
     */
    private array $metaqueue;

    /**
     * @var array<string,bool>
     */
    private array $moreFormatting = [];

    /**
     * Stores the major page components, for example <!--PAGE-->
     * Please keep the array keys as uppercase for consistency and
     * easy grepping.
     *
     * @var array<string,string>
     */
    private array $parts = [];

    private string $template;

    private string $themePath;

    /**
     * These are custom meta definitions parsed from M tags in the board template.
     *
     * @var array<string,string>
     */
    private array $userMetaDefs = [];

    /**
     * Stores page variables, like <%ismod%>.
     *
     * @var array<string,string>
     */
    private array $vars = [];

    public function __construct(
        private readonly DebugLog $debugLog,
        private readonly DomainDefinitions $domainDefinitions,
    ) {
        $this->themePath = $this->domainDefinitions->getDefaultThemePath();
    }

    public function addMeta(string $meta, string $content): void
    {
        $this->metaDefs[$meta] = $content;
    }

    public function addVar(string $varName, string $value): void
    {
        $this->vars['<%' . $varName . '%>'] = $value;
    }

    public function append(string $part, string $content): void
    {
        if (!array_key_exists($part, $this->parts)) {
            $this->reset($part, $content);

            return;
        }

        $this->parts[$part] .= $content;
    }

    public function load(string $file): void
    {
        $this->template = file_get_contents($file) ?: '';
        $this->template = (string) preg_replace_callback(
            '@<M name=([\'"])([^\'"]+)\1>(.*?)</M>@s',
            $this->userMetaParse(...),
            $this->template,
        );
    }

    public function loadMeta(string $component): void
    {
        $component = mb_strtolower($component);
        $themeComponentDir = $this->themePath . '/views/' . $component;
        $defaultComponentDir = $this->domainDefinitions->getDefaultThemePath() . '/views/' . $component;

        $componentDir = match (true) {
            is_dir($themeComponentDir) => $themeComponentDir,
            is_dir($defaultComponentDir) => $defaultComponentDir,
            default => null,
        };

        if ($componentDir === null) {
            return;
        }

        $this->metaqueue[] = $componentDir;
        $this->debugLog->log("Added {$component} to queue");
    }

    /**
     * @param array<mixed> ...$args
     */
    public function meta(string $meta, ...$args): string
    {
        $this->processQueue($meta);
        $formatted = vsprintf(
            str_replace(
                ['<%', '%>'],
                ['<%%', '%%>'],
                $this->userMetaDefs[$meta] ?? $this->metaDefs[$meta] ?? '',
            ),
            $args,
        );

        if (array_key_exists($meta, $this->moreFormatting)) {
            return $this->metaExtended($formatted);
        }

        return $formatted;
    }

    public function metaExists(string $meta): bool
    {
        return isset($this->userMetaDefs[$meta])
            || isset($this->metaDefs[$meta]);
    }

    public function reset(string $part, string $content = ''): void
    {
        $this->parts[$part] = $content;
    }

    public function render(): string
    {
        $header = ['CSS', 'SCRIPT', 'TITLE'];

        $html = $this->template;
        foreach ($this->parts as $part => $contents) {
            if (!in_array($part, $header, true)) {
                $contents = '<div id="' . mb_strtolower($part) . '">' . $contents . '</div>';
            }

            $html = str_replace("<!--{$part}-->", $contents, $html);
        }

        $html = $this->filtervars($html);
        if ($this->checkExtended($html, null)) {
            return $this->metaExtended($html);
        }

        return $html;
    }

    public function setThemePath(string $themePath): void
    {
        $this->themePath = $themePath;
    }

    public function has(string $part): false|int
    {
        return preg_match("/<!--{$part}-->/i", $this->template);
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

    /**
     * Given a component directory, loads all of the HTML views in it.
     *
     * @return array<string,string> Map of metaName => view HTML
     */
    private function loadComponentTemplates(string $componentDir): array
    {
        return array_reduce(glob($componentDir . '/*.html') ?: [], function (array $meta, $metaFile) {
            $metaName = pathinfo($metaFile, PATHINFO_FILENAME);
            $metaContent = file_get_contents($metaFile);

            if (!is_string($metaContent)) {
                return $meta;
            }

            $this->checkExtended($metaContent, $metaName);
            $meta[$metaName] = $metaContent;

            return $meta;
        }, []);
    }

    /**
     * Processes conditionals in the template.
     */
    private function metaExtended(string $content): string
    {
        return (string) preg_replace_callback(
            '@{if ([^}]+)}(.*){/if}@Us',
            $this->metaExtendedIfCB(...),
            $this->filtervars($content),
        );
    }

    /**
     * Parses conditions that look like this:
     *
     * {if <%isguest%>!=true&&%1$s="Sean"}Hello Sean{/if}
     *
     * @param array<string> $match 1 is the statement, 2 is the contents wrapped by the if
     */
    // phpcs:ignore SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
    private function metaExtendedIfCB(array $match): string
    {
        [, $statement, $content] = $match;

        $logicalOperator = str_contains($statement, '||')
            ? '||'
            : '&&';

        foreach (explode($logicalOperator, $statement) as $piece) {
            preg_match('@(\S+?)\s*([!><]?=|[><])\s*(\S*)@', $piece, $args);
            [, $left, $operator, $right] = $args;


            $conditionPasses = match ($operator) {
                '=' => $left === $right,
                '!=' => $left !== $right,
                '>=' => $left >= $right,
                '>' => $left > $right,
                '<=' => $left <= $right,
                '<' => $left < $right,
                default => false,
            };

            if ($logicalOperator === '&&' && !$conditionPasses) {
                break;
            }

            if ($logicalOperator === '||' && $conditionPasses) {
                break;
            }
        }

        return $conditionPasses ? $content : '';
    }

    private function filtervars(string $string): string
    {
        return str_replace(array_keys($this->vars), array_values($this->vars), $string);
    }

    /**
     * Loads any components requested so far.
     */
    private function processQueue(string $process): void
    {
        while ($componentDir = array_pop($this->metaqueue)) {
            $defaultComponentDir = str_replace(
                $this->themePath,
                $this->domainDefinitions->getDefaultThemePath(),
                $componentDir,
            );

            $component = pathinfo($componentDir, PATHINFO_BASENAME);
            $this->debugLog->log("{$process} triggered {$component} to load");


            $metaDefs = array_merge(
                // Load default templates for component first
                $this->loadComponentTemplates($defaultComponentDir),
                // Then override with any overrides from the theme
                $this->loadComponentTemplates($componentDir),
            );

            $this->metaDefs = array_merge($metaDefs, $this->metaDefs);
        }
    }

    /**
     * @param array<int,string> $match
     */
    private function userMetaParse(array $match): string
    {
        $this->checkExtended($match[3], $match[2]);
        $this->userMetaDefs[$match[2]] = $match[3];

        return '';
    }
}
