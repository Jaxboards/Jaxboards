<?php

declare(strict_types=1);

namespace ACP;

use Jax\DomainDefinitions;
use Jax\Request;

use function array_keys;
use function array_map;
use function file_get_contents;
use function header;
use function mb_strtolower;
use function mb_substr;
use function pathinfo;
use function str_replace;

use const PATHINFO_EXTENSION;
use const PHP_EOL;

final class Page
{
    /**
     * @var array<string,string>
     */
    private array $parts = [
        'content' => '',
        'sidebar' => '',
        'title' => '',
    ];

    public function __construct(
        private readonly DomainDefinitions $domainDefinitions,
        private readonly Request $request,
    ) {}

    public function append(string $partName, string $content): void
    {
        if (!isset($this->parts[$partName])) {
            $this->parts[$partName] = '';
        }

        $this->parts[$partName] .= $content;
    }

    /**
     * @param array<string,string> $links
     */
    public function sidebar(array $links): void
    {
        $content = '';
        $act = (string) $this->request->get('act');
        foreach ($links as $do => $title) {
            $content .= $this->parseTemplate(
                'sidebar-list-link.html',
                [
                    'title' => $title,
                    'url' => "?act={$act}&do={$do}",
                ],
            );
        }

        $this->parts['sidebar'] = $this->parseTemplate(
            'sidebar.html',
            [
                'content' => $this->parseTemplate(
                    'sidebar-list.html',
                    [
                        'content' => $content,
                    ],
                ),
            ],
        );
    }

    public function addContentBox(string $title, string $content): void
    {
        $this->append('content', $this->parseTemplate(
            'content-box.html',
            [
                'content' => $content,
                'title' => $title,
            ],
        ));
    }

    public function out(): void
    {
        $data = $this->parts;

        $boardURL = $this->domainDefinitions->getBoardURL();
        $data['css_url'] = $boardURL . '/ACP/css/css.css';
        $data['bbcode_css_url'] = $boardURL . '/Service/Themes/Default/bbcode.css';
        $data['themes_css_url'] = $boardURL . '/ACP/css/themes.css';
        $data['admin_js_url'] = $boardURL . '/dist/acp.js';

        echo $this->parseTemplate(
            'admin.html',
            $data,
        );
    }

    public function error(string $content): string
    {
        return $this->parseTemplate(
            'error.html',
            [
                'content' => $content,
            ],
        );
    }

    public function success(string $content): string
    {
        return $this->parseTemplate(
            'success.html',
            [
                'content' => $content,
            ],
        );
    }

    /**
     * Redirect the user and halt execution.
     *
     * @SuppressWarnings("ExitExpression")
     */
    public function location(string $location): void
    {
        header("Location: {$location}");

        exit;
    }

    /**
     * Parse a template file, replacing {{ key }} with the value of $data['key'].
     *
     * @param string                   $templateFile the path to the template file
     * @param array<string,int|string> $data         Template variables to be replaced
     *
     * @return string returns the template with the data replaced
     */
    public function parseTemplate(
        string $templateFile,
        array $data = [],
    ): string {
        $templateFile = 'views/' . $templateFile;

        if (pathinfo($templateFile, PATHINFO_EXTENSION) !== 'html') {
            if (mb_substr($templateFile, -1) !== '.') {
                $templateFile .= '.';
            }

            $templateFile .= 'html';
        }

        $template = file_get_contents($templateFile);

        if ($template === false) {
            return '';
        }

        return str_replace(
            array_map(static fn($name): string => '{{ ' . mb_strtolower($name) . ' }}', array_keys($data)),
            array_map(static fn($content): string => "{$content}", $data),
            $template,
        ) . PHP_EOL;
    }
}
