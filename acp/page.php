<?php

class PAGE
{
    public $CFG = array();
    public $parts = array();
    public $partparts = array();

    /**
     * Constructor for the PAGE object
     */
    public function __construct()
    {
        $this->parts = array(
            'title' => '',
            'sidebar' => '',
            'content' => ''
        );
        $this->partparts = array(
            'nav' => '',
            'navdropdowns' => ''
        );
    }

    /**
     * Creates a nav menu in the ACP.
     *
     * @param string $title The name of the button
     * @param string $page  The URL the button links to
     * @param array  $menu  A list of links and associated labels to print
     *                      out as a drop down list
     *
     * @return void
     */
    public function addNavmenu($title, $page, $menu)
    {
        $this->partparts['nav'] .= $this->parseTemplate(
            'nav-link.html',
            array(
                'page' => $page,
                'class' => mb_strtolower($title),
                'title' => $title,
            )
        ) . PHP_EOL;

        $navDropdownLinksTemplate = '';
        foreach ($menu as $menu_url => $menu_title) {
            $navDropdownLinksTemplate .= $this->parseTemplate(
                'nav-dropdown-link.html',
                array(
                    'url' => $menu_url,
                    'title' => $menu_title,
                )
            ) . PHP_EOL;
        }

        $this->partparts['navdropdowns'] .= $this->parseTemplate(
            'nav-dropdown.html',
            array(
                'dropdown_id' => 'menu_' . mb_strtolower($title),
                'dropdown_links' => $navDropdownLinksTemplate,
            )
        ) . PHP_EOL;
    }

    public function append($a, $b)
    {
        $this->parts[$a] = $b;
    }

    public function sidebar($sidebar)
    {
        if ($sidebar) {
            $this->parts['sidebar'] = $this->parseTemplate(
                'sidebar.html',
                array(
                    'content' => $sidebar,
                )
            );
        } else {
            $this->parts['sidebar'] = '';
        }
    }

    public function title($title)
    {
        $this->parts['title'] = $title;
    }

    public function addContentBox($title, $content)
    {
        $this->parts['content'] .= $this->parseTemplate(
            'content-box.html',
            array(
                'title' => $title,
                'content' => $content,
            )
        );
    }

    public function out()
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
            array(
                'nav' => $this->partparts['nav'],
                'nav_dropdowns' => $this->partparts['navdropdowns'],
            )
        );
        $data['css_url'] = BOARDURL . 'acp/css/css.css';
        $data['bbcode_css_url'] = BOARDURL . 'Service/Themes/Default/bbcode.css';
        $data['themes_css_url'] = BOARDURL . 'acp/css/themes.css';
        $data['admin_js_url'] = BOARDURL . 'dist/acp.js';

        echo $this->parseTemplate(
            'admin.html',
            $data
        );
    }

    public function back()
    {
        return $this->parseTemplate(
            'back.html',
            array()
        );
    }

    public function error($a)
    {
        return $this->parseTemplate(
            'error.html',
            array(
                'content' => $a,
            )
        );
    }

    public function success($a)
    {
        return $this->parseTemplate(
            'success.html',
            array(
                'content' => $a,
            )
        );
    }

    public function location($a)
    {
        header("Location: ${a}");
    }

    public function writeData($page, $name, $data, $mode = 'w')
    {
        $data_string = json_encode($data);
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
$${name} = json_decode(
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

    public function writeCFG($data)
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
     * Parse a template file, replacing <% TAGS %> with the appropriate $data
     *
     * @param string $templtaeFile  The path to the template file. Paths
     *                              that don't start with a '/' character
     *                              will start searching in the
     *                              JAXBOARDS_ROOT/acp/views/
     *                              directory.
     * @param array  $data          A key => value array, where <% KEY %>
     *                              is replaced by value
     *
     * @return string Returns the template with the data replaced.
     */
    public function parseTemplate($templateFile, $data = null)
    {
        if (mb_substr($templateFile, 0, 1) !== '/') {
            $templateFile = JAXBOARDS_ROOT . '/acp/views/' . $templateFile;
        }

        $fileError = false;
        if (is_file($templateFile)) {
            try {
                $template = file_get_contents($templateFile);
            } catch (Exception $e) {
                $fileError = true;
            }
            if (false === $template) {
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
                    '{{ ' . mb_strtoupper($name) . ' }}',
                    $content,
                    $template
                );
            }
        }
        return $template;
    }
}
