<?php

namespace Jax;

use InvalidArgumentException;

class Template {
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

    private ?string $themePath = null;

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
        $this->template = file_get_contents($file);
        $this->template = preg_replace_callback(
            '@<M name=([\'"])([^\'"]+)\1>(.*?)</M>@s',
            $this->userMetaParse(...),
            (string) $this->template,
        );
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
        $this->debugLog->log("Added {$component} to queue");
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
            throw new InvalidArgumentException($meta . ' has too many arguments');
        }

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
            if (!in_array($part, $header)) {
                $contents = '<div id="' . mb_strtolower($part) . '">' . $contents . '</div>';
            }

            $html = str_replace("<!--{$part}-->", $contents, $html);
        }

        $html = $this->filtervars($html);
        if ($this->checkExtended($html, null)) {
            $html = $this->metaExtended($html);
        }

        return $html;
    }

    public function setThemePath(string $themePath) {
        $this->themePath = $themePath;
    }

    public function has(string $part): false|int
    {
        return preg_match("/<!--{$part}-->/i", (string) $this->template);
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
     * @param mixed $componentDir
     *
     * @return array<string,string> Map of metaName => view HTML
     */
    private function loadComponentTemplates($componentDir): array
    {
        return array_reduce(glob($componentDir . '/*.html'), function ($meta, $metaFile) {
            $metaName = (string) pathinfo($metaFile, PATHINFO_FILENAME);
            $metaContent = file_get_contents($metaFile);

            if (!is_string($metaContent)) {
                return $meta;
            }

            $this->checkExtended($metaContent, $metaName);
            $meta[$metaName] = $metaContent;

            return $meta;
        }, []);
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

    private function userMetaParse(array $match): string
    {
        $this->checkExtended($match[3], $match[2]);
        $this->userMetaDefs[$match[2]] = $match[3];

        return '';
    }
}
