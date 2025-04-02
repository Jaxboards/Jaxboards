<?php

declare(strict_types=1);

final class PAGE
{
    public $CFG = [];

    public $parts = [
        'content' => '',
        'sidebar' => '',
        'title' => '',
    ];

    public $partparts = [
        'nav' => '',
        'navdropdowns' => '',
    ];

    /**
     * Creates a nav menu in the ACP.
     *
     * @param string $title The name of the button
     * @param string $page  The URL the button links to
     * @param array  $menu  A list of links and associated labels to print
     *                      out as a drop down list
     */
    public function addNavmenu($title, $page, $menu): void
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
        foreach ($menu as $menu_url => $menu_title) {
            $navDropdownLinksTemplate .= $this->parseTemplate(
                'nav-dropdown-link.html',
                [
                    'title' => $menu_title,
                    'url' => $menu_url,
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

    public function append($a, $b): void
    {
        $this->parts[$a] = $b;
    }

    public function sidebar($sidebar): void
    {
        $this->parts['sidebar'] = $sidebar ? $this->parseTemplate(
            'sidebar.html',
            [
                'content' => $sidebar,
            ],
        ) : '';
    }

    public function title($title): void
    {
        $this->parts['title'] = $title;
    }

    public function addContentBox($title, $content): void
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
        $data['css_url'] = BOARDURL . 'acp/css/css.css';
        $data['bbcode_css_url'] = BOARDURL . 'Service/Themes/Default/bbcode.css';
        $data['themes_css_url'] = BOARDURL . 'acp/css/themes.css';
        $data['admin_js_url'] = BOARDURL . 'dist/acp.js';

        echo $this->parseTemplate(
            'admin.html',
            $data,
        );
    }

    public function back(): ?string
    {
        return $this->parseTemplate(
            'back.html',
            [],
        );
    }

    public function error($a): ?string
    {
        return $this->parseTemplate(
            'error.html',
            [
                'content' => $a,
            ],
        );
    }

    public function success($a): ?string
    {
        return $this->parseTemplate(
            'success.html',
            [
                'content' => $a,
            ],
        );
    }

    public function location($a): void
    {
        header("Location: {$a}");
    }

    public function writeData($page, $name, $data, $mode = 'w'): string
    {
        $data_string = json_encode($data, JSON_PRETTY_PRINT);
        $write = <<<EOT
            <?php
            /**
             * JaxBoards config file. It's just JSON embedded in PHP- wow!
             *
             * PHP Version 5.3.0
             *
             * @category Jaxboards
             * @package  Jaxboards
             *
             * @author  Sean Johnson <seanjohnson08@gmail.com>
             * @author  World's Tallest Ladder <wtl420@users.noreply.github.com>
             * @license MIT <https://opensource.org/licenses/MIT>
             *
             * @link https://github.com/Jaxboards/Jaxboards Jaxboards on Github
             */
            \${$name} = json_decode(
            <<<'EOD'
            {$data_string}
            EOD
                ,
                true
            );
            EOT;
        $file = fopen($page, $mode);
        fwrite($file, $write);
        fclose($file);

        return $write;
    }

    public function writeCFG($data): string
    {
        include BOARDPATH . 'config.php';
        foreach ($data as $k => $v) {
            $CFG[$k] = $v;
        }

        $this->CFG = $CFG;

        return $this->writeData(BOARDPATH . 'config.php', 'CFG', $this->CFG);
    }

    public function getCFGSetting($setting)
    {
        if (!$this->CFG) {
            include BOARDPATH . 'config.php';
            $this->CFG = $CFG;
        }

        return @$this->CFG[$setting];
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
    public function parseTemplate($templateFile, $data = null): ?string
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

        $fileError = false;
        if (is_file($templateFile)) {
            try {
                $template = file_get_contents($templateFile);
            } catch (Exception) {
                $fileError = true;
            }

            if ($template === false) {
                $fileError = true;
            }
        } else {
            $fileError = true;
        }

        if ($fileError) {
            error_log('Could not open file: ' . $templateFile);

            return '';
        }

        if (is_array($data)) {
            foreach ($data as $name => $content) {
                $template = str_replace(
                    '{{ ' . mb_strtolower($name) . ' }}',
                    "{$content}",
                    $template,
                );
            }
        }

        // Blank out other template variables.
        return preg_replace('/{{\s+.+\s+}}/', '', $template);
    }
}
