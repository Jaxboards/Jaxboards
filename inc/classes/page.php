<?php

class PAGE
{
    public $JSOutput = array();
    public $debuginfo = '';
    public $jsaccess = false;
    public $jsdirectlink = false;
    public $jsnewloc = false;
    public $jsnewlocation = false;
    public $jsupdate = false;
    public $metadefs = array();
    public $mobile = false;
    public $moreFormatting = array();
    public $parts = array();
    public $userMetaDefs = array();
    public $vars = array();

    public function __construct()
    {
        $this->jsaccess = isset($_SERVER['HTTP_X_JSACCESS']) ?
            $_SERVER['HTTP_X_JSACCESS'] : false;
        $this->jsupdate = (1 == $this->jsaccess);
        $this->jsnewlocation = $this->jsnewloc = ($this->jsaccess >= 2);
        $this->jsdirectlink = (3 == $this->jsaccess);
        $this->mobile = false !== mb_stripos($_SERVER['HTTP_USER_AGENT'], 'mobile');
    }

    public function get($a)
    {
        return $this->parts[$a];
    }

    public function append($a, $b)
    {
        if ('SCRIPT' === mb_strtoupper($a) && $this->mobile) {
            return;
        }
        $a = mb_strtoupper($a);
        if (!$this->jsaccess || 'TITLE' === $a) {
            if (!isset($this->parts[$a])) {
                return $this->reset($a, $b);
            }

            return $this->parts[$a] .= $b;
        }
    }

    public function addvar($a, $b)
    {
        $this->vars['{{ ' . $a . ' }}'] = $b;
    }

    public function filtervars($matches)
    {
        return str_replace(
            array_keys($this->vars),
            array_values($this->vars),
            $matches
        );
    }

    public function prepend($a, $b)
    {
        if (!$this->jsaccess) {
            $a = mb_strtoupper($a);
            if (!isset($this->parts[$a])) {
                return $this->reset($a, $b);
            }

            return $this->parts[$a] = $b . $this->parts[$a];
        }
    }

    public function location($a)
    {
        global $PAGE,$SESS,$JAX;
        if (empty($JAX->c) && '?' === mb_substr($a, 0, 1)) {
            $query = array();
            parse_str(mb_substr($a, 1), $query);
            if (isset($SESS->data['id'])) {
                $query['sessid'] = $SESS->data['id'];
            }
            $a .= '?' . http_build_query($query);
        }
        if ($PAGE->jsaccess) {
            $PAGE->JS('location', $a);
        } else {
            header("Location: ${a}");
        }
    }

    public function reset($a, $b = '')
    {
        $a = mb_strtoupper($a);
        $this->parts[$a] = $b;
    }

    public function JS()
    {
        $args = func_get_args();
        if ('softurl' == $args[0]) {
            $GLOBALS['SESS']->erase('location');
        }
        if ($this->jsaccess) {
            $this->JSOutput[] = $args;
        }
    }

    public function JSRaw($a)
    {
        foreach (explode(PHP_EOL, $a) as $a22) {
            $a2 = json_decode($a22);
            if (!is_array($a2)) {
                continue;
            }
            if (is_array($a2[0])) {
                foreach ($a2 as $v) {
                    $this->JSOutput[] = $v;
                }
            } else {
                $this->JSOutput[] = $a2;
            }
        }
    }

    public function JSRawArray($a)
    {
        $this->JSOutput[] = $a;
    }

    public function out()
    {
        global $SESS,$JAX;
        if (isset($this->done)) {
            return false;
        }
        $this->done = true;
        $this->parts['path']
            = "<div id='path' class='path'>" . $this->buildpath() . '</div>';

        if ($this->jsaccess) {
            header('Content-type:text/plain');
            foreach ($this->JSOutput as $k => $v) {
                $this->JSOutput[$k] = $SESS->addSessID($v);
            }
            echo !empty($this->JSOutput) ? $JAX::json_encode($this->JSOutput) : '';
        } else {
            $autobox = array('page', 'copyright', 'userbox');
            foreach ($this->parts as $name => $value) {
                $name = mb_strtolower($name);
                if (in_array($name, $autobox)) {
                    $v = '<div id="' . $name . '">' . $value . '</div>';
                }
                if ('path' === $name) {
                    $this->template
                        = preg_replace('@{{ path }}@', $value, $this->template, 1);
                }
                $this->template = str_replace('{{ ' . $name . ' }}', $value, $this->template);
            }
            $this->template = $this->filtervars($this->template);
            $this->template = $SESS->addSessId($this->template);
            if ($this->checkextended($this->template, null)) {
                $this->template = $this->metaextended($this->template);
            }
            echo $this->template;
        }
    }

    public function collapsebox($a, $b, $c = false)
    {
        return $this->meta('collapsebox', ($c ? ' id="' . $c . '"' : ''), $a, $b);
    }

    public function error($a)
    {
        return $this->meta('error', $a);
    }

    public function templatehas($a)
    {
        return preg_match("/{{ ${a} }}/i", $this->template);
    }

    public function loadtemplate($a)
    {
        $this->template = file_get_contents($a);
        $this->template = preg_replace_callback(
            '/\{\{ [\'"](\w+)[\'"]\|jax_include \}\}/',
            array(
                $this,
                'includer',
            ),
            $this->template
        );
        $this->template = preg_replace_callback(
            '@{% block ([\w_]+) %}(.*?){% endblock %}@s',
            array(
                $this,
                'userMetaParse',
            ),
            $this->template
        );
    }

    public function loadskin($id)
    {
        global $DB,$CFG;
        $skin = array();
        if ($id) {
            $result = $DB->safeselect(
                'title,custom,wrapper',
                'skins',
                'WHERE id=? LIMIT 1',
                $id
            );
            $skin = $DB->arow($result);
            $DB->disposeresult($result);
        }
        if (empty($skin)) {
            $result = $DB->safeselect(
                'title,custom,wrapper',
                'skins',
                'WHERE `default`=1 LIMIT 1'
            );
            $skin = $DB->arow($result);
            $DB->disposeresult($result);
        }
        if (!$skin) {
            $skin = array(
                'title' => 'Default',
                'custom' => 0,
                'wrapper' => false,
            );
        }
        $t = ($skin['custom'] ? BOARDPATH : '') . 'Themes/' . $skin['title'] . '/';
        $turl = ($skin['custom'] ? BOARDPATHURL : '') . 'Themes/' . $skin['title'] . '/';
        if (is_dir($t)) {
            define('THEMEPATH', $t);
            define('THEMEPATHURL', $turl);
        } else {
            define('THEMEPATH', JAXBOARDS_ROOT . '/' . $CFG['dthemepath']);
            define('THEMEPATHURL', BOARDURL . $CFG['dthemepath']);
        }
        define('DTHEMEPATH', JAXBOARDS_ROOT . '/' . $CFG['dthemepath']);
        $this->loadtemplate(
            $skin['wrapper'] ?
            BOARDPATH . 'Wrappers/' . $skin['wrapper'] . '.txt' :
            THEMEPATH . 'wrappers.txt'
        );
    }

    public function userMetaParse($matches)
    {
        $this->checkextended($matches[2], $matches[1]);
        $this->userMetaDefs[$matches[1]] = $matches[2];
    }

    public function includer($matches)
    {
        global $DB;

        if (isset($matches[1])) {
            $match = $matches[1];
            $result = $DB->safeselect(
                'page',
                'pages',
                'WHERE `act`=?',
                $DB->basicvalue($match)
            );
            $page = $DB->arow($result);
            if ($page) {
                $page = array_shift($page);
            }
            $DB->disposeresult($result);

            return $page ? $page : '';
        }

        return '';
    }

    public function loadmeta($component)
    {
        $component = mb_strtolower($component);
        $themeComponentDir = THEMEPATH . 'views/' . $component;
        if (is_dir($themeComponentDir)) {
            $componentDir = $themeComponentDir;
        } else {
            $componentDir = DTHEMEPATH . 'views/' . $component;
            if (!is_dir($componentDir)) {
                $componentDir = false;
            }
        }
        if (false !== $componentDir) {
            $this->metaqueue[] = $componentDir;
            $this->debug("Added ${component} to queue");
        }
    }

    public function processqueue($process)
    {
        while ($componentDir = array_pop($this->metaqueue)) {
            $component = pathinfo($componentDir, PATHINFO_BASENAME);
            $this->debug("${process} triggered ${component} to load");
            $meta = array();
            foreach (glob($componentDir . '/*.html') as $metaFile) {
                $metaName = pathinfo($metaFile, PATHINFO_FILENAME);
                $metaContent = file_get_contents($metaFile);
                $this->checkextended($metaContent, $metaName);
                $meta[$metaName] = $metaContent;
            }
            // Check default components for anything missing.
            $defaultComponentDir = str_replace(
                THEMEPATH,
                DTHEMEPATH,
                $componentDir
            );
            if ($defaultComponentDir !== $componentDir) {
                foreach (glob($defaultComponentDir . '/*.html') as $metaFile) {
                    $metaName = pathinfo($metaFile, PATHINFO_FILENAME);
                    $metaContent = file_get_contents($metaFile);
                    $this->checkextended($metaContent, $metaName);
                    if (!isset($meta[$metaName])) {
                        $meta[$metaName] = $metaContent;
                    }
                }
            }
            $this->metadefs = $meta + $this->metadefs;
        }
    }

    public function meta()
    {
        $args = func_get_args();
        $meta = array_shift($args);
        $args = isset($args[0]) && is_array($args[0]) ? $args[0] : $args;
        if (!is_array($args)) {
            $args = array();
        }
        $this->processqueue($meta);
        $content = '';
        if (isset($this->userMetaDefs[$meta])) {
            $content .= $this->userMetaDefs[$meta];
        } elseif (isset($this->metadefs[$meta])) {
            $content .= $this->metadefs[$meta];
        }
        $content = str_replace(
            array('{%%', '%%}'),
            array('{%', '%}'),
            vsprintf(
                str_replace(
                    array('{%', '%}'),
                    array('{%%', '%%}'),
                    $content
                ),
                $args
            )
        );
        if (false === $content) {
            die($meta . ' has too many arguments');
        }
        if (isset($this->moreFormatting[$meta])
            && $this->moreFormatting[$meta]
        ) {
            return $this->metaextended($content);
        }

        return $content;
    }

    public function metaextended($meta)
    {
        return preg_replace_callback(
            '@\{% if (.+) %\}(.*)\{% endif %\}@Us',
            array(
                $this,
                'metaextendedifcb',
            ),
            $this->filtervars($meta)
        );
    }

    public function metaextendedifcb($matches)
    {
        if (false !== mb_strpos($matches[1], '||')) {
            $separator = '||';
        } else {
            $separator = '&&';
        }
        $condition = false;
        foreach (explode($separator, $matches[1]) as $piece) {
            $pieces = array();
            preg_match('@(\\S+?)\\s*([!><]?=|[><])\\s*(\\S*)@', $piece, $pieces);
            if (!isset($pieces[2])) {
                continue;
            }
            switch ($pieces[2]) {
                case '=':
                    $condition = $pieces[1] == $pieces[3];
                    break;
                case '!=':
                    $condition = $pieces[1] != $pieces[3];
                    break;
                case '>=':
                    $condition = $pieces[1] >= $pieces[3];
                    break;
                case '>':
                    $condition = $pieces[1] > $pieces[3];
                    break;
                case '<=':
                    $condition = $pieces[1] <= $pieces[3];
                    break;
                case '<':
                    $condition = $pieces[1] < $pieces[3];
                    break;
            }
            if ('&&' == $separator && !$condition) {
                break;
            }
            if ('||' == $separator && $condition) {
                break;
            }
        }
        if ($condition) {
            return $matches[2];
        }

        return '';
    }

    public function checkextended($data, $meta = null)
    {
        if (false !== mb_strpos($data, '{% if ')) {
            if ($meta) {
                $this->moreFormatting[$meta] = true;
            } else {
                return true;
            }
        }

        return false;
    }

    public function metaexists($meta)
    {
        return isset($this->userMetaDefs[$meta])
            || isset($this->metadefs[$meta]);
    }

    public function path($a)
    {
        if (!isset($this->parts['path'])
            || !is_array($this->parts['path'])
        ) {
            $this->parts['path'] = array();
        }
        $empty = empty($this->parts['path']);
        foreach ($a as $value => $link) {
            $this->parts['path'][$link] = $value;
        }

        return true;
    }

    public function buildpath()
    {
        $first = true;
        $path = '';
        foreach ($this->parts['path'] as $value => $link) {
            $path .= $this->meta(
                $first
                && $this->metaexists('path-home') ? 'path-home' : 'path-part',
                $value,
                $link
            );
            $first = false;
        }

        return $this->meta('path', $path);
    }

    public function updatepath($a = false)
    {
        if ($a) {
            $this->path($a);
        }
        $this->JS('update', 'path', $this->buildpath());
    }

    public function debug($data = '')
    {
        if ($data) {
            $this->debuginfo .= $data . '<br />';
        } else {
            return $this->debuginfo;
        }
    }
}
