<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

use function array_filter;
use function array_key_exists;
use function array_map;
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

    private Environment $twigEnvironment;

    private FilesystemLoader $filesystemLoader;

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

    /**
     * @param array<mixed> $context
     */
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
        $addWrapperDiv = [
            'NAVIGATION',
            'LOGO',
            'USERBOX',
            'PATH',
            'PAGE',
            'FOOTER',
        ];

        $html = $this->template;
        foreach ($this->parts as $part => $contents) {
            if (in_array($part, $addWrapperDiv, true)) {
                $contents = '<div id="' . mb_strtolower($part) . '">' . $contents . '</div>';
            }

            $html = str_replace("<!--{$part}-->", $contents, $html);
        }

        return $html;
    }

    public function setThemePath(string $themePath): void
    {
        $paths = array_filter(
            [
                $this->fileSystem->pathJoin($themePath, 'views'),
                $this->fileSystem->pathJoin($this->domainDefinitions->getDefaultThemePath(), 'views'),
            ],
            fn(string $dir): bool => $this->fileSystem->getFileInfo($dir)->isDir(),
        );

        $paths = array_map($this->fileSystem->pathFromRoot(...), $paths);

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

        array_map($this->twigEnvironment->addFunction(...), [
            new TwigFunction('url', fn(...$args) => $this->container->get(Router::class)->url(...$args)),
        ]);

        array_map($this->twigEnvironment->addFilter(...), [
            // TODO: combine date helpers
            new TwigFilter(
                'autoDate',
                fn(?string $string) => $this->container->get(Date::class)->autoDate($string),
                ['is_safe' => ['html']],
            ),
            new TwigFilter(
                'smallDate',
                fn(?string $string) => $string ? $this->container->get(Date::class)->smallDate($string) : '',
                ['is_safe' => ['html']],
            ),
            new TwigFilter('slugify', fn(?string $string) => $this->container->get(TextFormatting::class)->slugify(
                $string,
            )),
            new TwigFilter('textOnly', fn(?string $string) => $this->container->get(TextFormatting::class)->textOnly(
                $string,
            )),
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
            new TwigFilter('wordFilter', fn(?string $string) => $this->container->get(TextFormatting::class)->wordFilter(
                $string,
            )),
        ]);
    }
}
