<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_unshift;
use function array_values;
use function in_array;
use function mb_strtolower;
use function preg_match;
use function str_replace;

/**
 * This class is entirely responsible for rendering the page.
 */
final class Template
{
    /**
     * Stores the major page components, for example <!--PAGE-->
     * Please keep the array keys as uppercase for consistency and
     * easy grepping.
     *
     * @var array<string,string>
     */
    private array $parts = [];

    private string $template;

    /**
     * Stores page variables, like <%ismod%>.
     *
     * @var array<string,string>
     */
    private array $vars = [];

    private readonly Environment $twigEnvironment;

    private readonly FilesystemLoader $filesystemLoader;

    public function __construct(
        private readonly Container $container,
        private readonly DomainDefinitions $domainDefinitions,
        private readonly FileSystem $fileSystem,
    ) {
        $this->initializeTwig();
        $this->setThemePath($this->domainDefinitions->getDefaultThemePath());
    }

    /**
     * Utility method for generating input[hidden].
     *
     * @param array<string,string> $fields
     */
    public static function hiddenFormFields(array $fields): string
    {
        $html = '';
        foreach ($fields as $key => $value) {
            $html .= "<input type='hidden' name='{$key}' value='{$value}'>";
        }

        return $html;
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
        $this->template = $this->fileSystem->getContents($file) ?: '';
    }

    public function render(string $name, array $context = []): string
    {
        return $this->twigEnvironment->render($name . '.html.twig', $context);
    }

    public function reset(string $part, string $content = ''): void
    {
        $this->parts[$part] = $content;
    }

    public function out(): string
    {
        $header = ['CSS', 'SCRIPT', 'TITLE'];

        $html = $this->template;
        foreach ($this->parts as $part => $contents) {
            if (!in_array($part, $header, true)) {
                $contents = '<div id="' . mb_strtolower($part) . '">' . $contents . '</div>';
            }

            $html = str_replace("<!--{$part}-->", $contents, $html);
        }

        return $this->replaceVars($html);
    }

    public function setThemePath(string $themePath): void
    {
        $paths = [$this->fileSystem->pathFromRoot($this->domainDefinitions->getDefaultThemePath(), 'views')];

        $themeViews = $this->fileSystem->pathJoin($themePath, 'views');
        if ($this->fileSystem->getFileInfo($themeViews)->isDir()) {
            // Custom skin needs higher priority
            array_unshift($paths, $themeViews);
        }

        $this->filesystemLoader->setPaths($paths);
    }

    public function has(string $part): false|int
    {
        return preg_match("/<!--{$part}-->/i", $this->template);
    }

    private function initializeTwig(): void
    {
        $this->filesystemLoader = new FilesystemLoader();
        $this->twigEnvironment = new Environment($this->filesystemLoader, [
            'cache' => $this->fileSystem->pathFromRoot('.cache/.twig.cache'),
        ]);

        array_map(
            $this->twigEnvironment->addFunction(...),
            [
                new TwigFunction('url', fn(...$args) => $this->container->get(Router::class)->url(...$args)),
            ],
        );

        array_map(
            $this->twigEnvironment->addFilter(...),
            [
                new TwigFilter(
                    'autoDate',
                    fn(?string $string) => $this->container->get(Date::class)->autoDate($string),
                    ['is_safe' => ['html']],
                ),
                new TwigFilter(
                    'slugify',
                    fn(?string $string) => $this->container->get(TextFormatting::class)->slugify($string),
                ),
                new TwigFilter(
                    'textOnly',
                    fn(?string $string) => $this->container->get(TextFormatting::class)->textOnly($string),
                ),
                new TwigFilter(
                    'theWorks',
                    fn(?string $string) => $this->container->get(TextFormatting::class)->theWorks($string),
                    ['is_safe' => ['html']],
                ),
                new TwigFilter(
                    'theWorksInline',
                    fn(?string $string) => $this->container->get(TextFormatting::class)->theWorksInline($string),
                    ['is_safe' => ['html']],
                ),
                new TwigFilter(
                    'wordFilter',
                    fn(?string $string) => $this->container->get(TextFormatting::class)->wordFilter($string),
                ),
            ],
        );
    }

    private function replaceVars(string $string): string
    {
        return str_replace(array_keys($this->vars), array_values($this->vars), $string);
    }
}
