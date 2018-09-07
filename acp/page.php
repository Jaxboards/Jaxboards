<?php

class PAGE
{
    public $CFG;
    public $parts = array('sidebar' => '', 'content' => '');
    public $partparts = array('nav' => '', 'navdropdowns' => '');

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
        $this->partparts['nav'] .=
            '<a href="' . $page . '" class="' . mb_strtolower($title) . '">' . $title . '</a>';
        $this->partparts['navdropdowns'] .=
            '<div class="dropdownMenu" id="menu_' . mb_strtolower($title) . '">';
        foreach ($menu as $k => $v) {
            $this->partparts['navdropdowns'] .= '<a href="' . $k . '">' . $v . '</a>';
        }
        $this->partparts['navdropdowns'] .= '</div>';
    }

    public function append($a, $b)
    {
        $this->parts[$a] = $b;
    }

    public function sidebar($sidebar)
    {
        if ($sidebar) {
            $this->parts['sidebar']
                = "<div class='sidebar'><a href='?' class='icons home'>ACP Home</a>" .
                $sidebar . '</div>';
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
        $this->parts['content'] .=
            '<div class="box"><div class="header">' . $title .
            '</div><div class="content">' . $content . '</div></div>';
    }

    public function out()
    {
        $template = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "https://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="https://www.w3.org/1999/xhtml/" xml:lang="en" lang="en">
 <head>
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
  <link rel="stylesheet" type="text/css" href="' . BOARDURL .
        'Service/acp/Theme/css.css" />
  <link rel="stylesheet" type="text/css" href="' . BOARDURL .
        'Service/Themes/Default/bbcode.css" />
  <link rel="stylesheet" type="text/css"
    href="' . BOARDURL . 'acp/css/themes.css"/>
  <script type="text/javascript" src="' . BOARDURL .
        'dist/acp.js"></script>
  <title><% TITLE %></title>
 </head>
 <body>
  <a id="header" href="admin.php"></a>
  <div id="userbox">Logged in as: <% USERNAME %>
    <a href="../">Back to Board</a></div>
  <% NAV %>
  <div id="page">
    <% SIDEBAR %>
   <div class="right">
    <% CONTENT %>
   </div>
  </div>
 </body>
</html>';
        $this->parts['nav'] = '<div id="nav" onmouseover="dropdownMenu(event)">' .
            $this->partparts['nav'] . '</div>' . $this->partparts['navdropdowns'];
        foreach ($this->parts as $k => $v) {
            $template = str_replace('<% ' . mb_strtoupper($k) . ' %>', $v, $template);
        }
        echo $template;
    }

    public function back()
    {
        return "<a href='javascript:history.back()'>Back</a>";
    }

    public function error($a)
    {
        return "<div class='error'>${a}</div>";
    }

    public function success($a)
    {
        return "<div class='success'>${a}</div>";
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

        return $this->writeData(BOARDPATH . 'config.php', 'CFG', $CFG);
    }

    public function getCFGSetting($setting)
    {
        if (!$this->CFG) {
            include BOARDPATH . 'config.php';
            $this->CFG = $CFG;
        }

        return @$this->CFG[$setting];
    }
}
