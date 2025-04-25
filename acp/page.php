<?php

declare(strict_types=1);

namespace ACP;

use Exception;
use Jax\DomainDefinitions;

use function error_log;
use function file_get_contents;
use function header;
use function is_array;
use function is_file;
use function mb_strtolower;
use function mb_substr;
use function pathinfo;
use function preg_replace;
use function str_replace;

use const PATHINFO_EXTENSION;
use const PHP_EOL;

/**
 * @psalm-api
 */
final class Page
{
    /**
     * @var array<string,string>
     */
    private $parts = [
        'content' => '',
        'sidebar' => '',
        'title' => '',
    ];

    /**
     * @var array<string,string>
     */
    private $partparts = [
        'nav' => '',
        'navdropdowns' => '',
    ];

    public function __construct(
        private readonly DomainDefinitions $domainDefinitions,
    ) {}

    /**
     * Creates a nav menu in the ACP.
     *
     * @param string $title The name of the button
     * @param string $page  The URL the button links to
     * @param array  $menu  A list of links and associated labels to print
     *                      out as a drop down list
     */
    public function addNavmenu(string $title, string $page, array $menu): void
    {
        $this->partparts['nav'] .= $this->parseTemplate(
            'nav-link.html',
            [
                'class' => mb_strtolower($title),
                'page' => $page,
                'title' => $title,
            ],
        ) . PHP_EOL;

        $navDropdownLinksTemplate = '';
        foreach ($menu as $menuURL => $menuTitle) {
            $navDropdownLinksTemplate .= $this->parseTemplate(
                'nav-dropdown-link.html',
                [
                    'title' => $menuTitle,
                    'url' => $menuURL,
                ],
            ) . PHP_EOL;
        }

        $this->partparts['navdropdowns'] .= $this->parseTemplate(
            'nav-dropdown.html',
            [
                'dropdown_id' => 'menu_' . mb_strtolower($title),
                'dropdown_links' => $navDropdownLinksTemplate,
            ],
        ) . PHP_EOL;
    }

    public function append(string $partName, string $content): void
    {
        $this->parts[$partName] = $content;
    }

    public function sidebar(string $sidebarLinks): void
    {
        $this->parts['sidebar'] = $this->parseTemplate(
            'sidebar.html',
            [
                'content' => $this->parseTemplate(
                    'sidebar-list.html',
                    [
                        'content' => $sidebarLinks,
                    ],
                ),
            ],
        );
    }

    public function title(string $title): void
    {
        $this->parts['title'] = $title;
    }

    public function addContentBox(string $title, string $content): void
    {
        $this->parts['content'] .= $this->parseTemplate(
            'content-box.html',
            [
                'content' => $content,
                'title' => $title,
            ],
        );
    }

    public function out(): void
    {
        $data = $this->parts;

        if (!isset($this->partparts['nav'])) {
            $this->partparts['nav'] = '';
        }

        if (!isset($this->partparts['navdropdowns'])) {
            $this->partparts['navdropdowns'] = '';
        }

        $data['nav'] = $this->parseTemplate(
            'nav.html',
            [
                'nav' => $this->partparts['nav'],
                'nav_dropdowns' => $this->partparts['navdropdowns'],
            ],
        );
        $boardURL = $this->domainDefinitions->getBoardUrl();
        $data['css_url'] = $boardURL . 'acp/css/css.css';
        $data['bbcode_css_url'] = $boardURL . 'Service/Themes/Default/bbcode.css';
        $data['themes_css_url'] = $boardURL . 'acp/css/themes.css';
        $data['admin_js_url'] = $boardURL . 'dist/acp.js';

        echo $this->parseTemplate(
            'admin.html',
            $data,
        );
    }

    public function back(): ?string
    {
        return $this->parseTemplate(
            'back.html',
        );
    }

    public function error(string $content): ?string
    {
        return $this->parseTemplate(
            'error.html',
            [
                'content' => $content,
            ],
        );
    }

    public function success(string $content): ?string
    {
        return $this->parseTemplate(
            'success.html',
            [
                'content' => $content,
            ],
        );
    }

    public function location(string $location): void
    {
        header("Location: {$location}");
    }

    /**
     * Parse a template file, replacing {{ key }} with the value of $data['key'].
     *
     * @param string $templateFile The path to the template file. Paths
     *                             that don't start with a '/' character
     *                             will start searching in the
     *                             JAXBOARDS_ROOT/acp/views/
     *                             directory.
     * @param array  $data         A key => value array, where {{ key }}
     *                             is replaced by value
     *
     * @return string returns the template with the data replaced
     */
    public function parseTemplate(string $templateFile, array $data = []): ?string
    {
        if (mb_substr($templateFile, 0, 1) !== '/') {
            $templateFile = JAXBOARDS_ROOT . '/acp/views/' . $templateFile;
        }

        if (pathinfo($templateFile, PATHINFO_EXTENSION) !== 'html') {
            if (mb_substr($templateFile, -1) !== '.') {
                $templateFile .= '.';
            }

            $templateFile .= 'html';
        }

        $template = file_get_contents($templateFile);

        $template = str_replace(
            array_map(fn($name) => '{{ ' . mb_strtolower($name) . ' }}', array_keys($data)),
            array_map(fn($content) => "{$content}", $data),
            $template,
        );

        // Blank out other template variables.
        return preg_replace('/{{\s+.+\s+}}/', '', $template);
    }
}
