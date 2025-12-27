<?php

declare(strict_types=1);

namespace ACP;

use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function array_key_exists;
use function header;
use function mb_substr;

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

    private readonly Environment $twigEnvironment;

    private readonly FilesystemLoader $filesystemLoader;

    public function __construct(
        private readonly DomainDefinitions $domainDefinitions,
        private readonly FileSystem $fileSystem,
        private readonly Request $request,
    ) {
        $this->initializeTwig();
    }

    public function append(string $partName, string $content): void
    {
        if (!array_key_exists($partName, $this->parts)) {
            $this->parts[$partName] = '';
        }

        $this->parts[$partName] .= $content;
    }

    public function checked(bool $checked): string
    {
        return $checked ? ' checked="checked"' : '';
    }

    /**
     * @param array<string,string> $links
     */
    public function sidebar(array $links): void
    {
        $content = '';
        $act = $this->request->asString->get('act') ?? '';
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
     */
    public function location(string $location): void
    {
        header("Location: {$location}");
    }

    /**
     * Parse a template file, replacing {{ key }} with the value of $data['key'].
     *
     * @param string                         $templateFile the path to the template file
     * @param array<string,float|int|string> $data         Template variables to be replaced
     *
     * @return string returns the template with the data replaced
     */
    public function parseTemplate(
        string $templateFile,
        array $data = [],
    ): string {

        // Add .html extension if needed
        $fileInfo = $this->fileSystem->getFileInfo($templateFile);
        if ($fileInfo->getExtension() !== 'html') {
            if (mb_substr($templateFile, -1) !== '.') {
                $templateFile .= '.';
            }

            $templateFile .= 'html';
        }

        return $this->twigEnvironment->render($templateFile . '.twig', $data);
    }

    private function initializeTwig(): void
    {
        $this->filesystemLoader = new FilesystemLoader($this->fileSystem->pathFromRoot('ACP/views/'));
        $this->twigEnvironment = new Environment($this->filesystemLoader, [
            'cache' => $this->fileSystem->pathFromRoot('.cache/.twig.cache'),
            // TODO: autoescaping should be turned on, but we previously did it manually
            // and this is the easiest migration strategy at the moment
            'autoescape' => false,
        ]);
    }
}
