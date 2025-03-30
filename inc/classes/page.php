<?php

final class PAGE
{
    public $metadefs = [];

    public $debuginfo = '';

    public $JSOutput = [];

    /**
     * @var int
     */
    public $jsaccess = 0;

    /**
     * @var null|string Store the UCP page data
     */
    public ?string $ucppage = null;

    /**
     * @var bool
     */
    public $jsupdate = false;

    /**
     * @var bool
     */
    public $jsnewlocation = false;

    /**
     * @var bool
     */
    public $jsdirectlink = false;

    /**
     * @var bool
     */
    public $mobile = false;

    public $parts = [];

    public $vars = [];

    public $userMetaDefs = [];

    public $moreFormatting = [];

    public $template;

    public $metaqueue;

    public $done;

    public function __construct()
    {
        $this->jsaccess = (int) ($_SERVER['HTTP_X_JSACCESS'] ?? 0);

        if ($this->jsaccess !== 0) {
            $this->jsupdate = $this->jsaccess === 1;
            $this->jsnewlocation = $this->jsaccess >= 2;
            $this->jsdirectlink = $this->jsaccess === 3;
        }

        $this->mobile = str_contains((string) $_SERVER['HTTP_USER_AGENT'], 'mobile');
    }

    public function get($a)
    {
        return $this->parts[$a];
    }

    public function append($a, $b)
    {
        $a = mb_strtoupper((string) $a);
        if (!$this->jsaccess || $a === 'TITLE') {
            if (!isset($this->parts[$a])) {
                $this->reset($a, $b);

                return;
            }

            return $this->parts[$a] .= $b;
        }

        return null;
    }

    public function addvar($a, $b): void
    {
        $this->vars['<%' . $a . '%>'] = $b;
    }

    public function filtervars($a)
    {
        return str_replace(array_keys($this->vars), array_values($this->vars), $a);
    }

    public function prepend($a, $b)
    {
        if (!$this->jsaccess) {
            $a = mb_strtoupper((string) $a);
            if (!isset($this->parts[$a])) {
                $this->reset($a, $b);

                return;
            }

            return $this->parts[$a] = $b . $this->parts[$a];
        }

        return null;
    }

    public function location($a): void
    {
        global $PAGE,$SESS,$JAX;
        if (empty($JAX->c) && $a[0] === '?') {
            $a = '?sessid=' . $SESS->data['id'] . '&' . mb_substr((string) $a, 1);
        }

        if ($PAGE->jsaccess) {
            $PAGE->JS('location', $a);
        } else {
            header("Location: {$a}");
        }
    }

    public function reset($a, $b = ''): void
    {
        $a = mb_strtoupper((string) $a);
        $this->parts[$a] = $b;
    }

    public function JS(...$args): void
    {
        if ($args[0] === 'softurl') {
            $GLOBALS['SESS']->erase('location');
        }

        if (!$this->jsaccess) {
            return;
        }

        $this->JSOutput[] = $args;
    }

    public function JSRaw($a): void
    {
        foreach (explode(PHP_EOL, (string) $a) as $a22) {
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

    public function JSRawArray($a): void
    {
        $this->JSOutput[] = $a;
    }

    public function out(): ?bool
    {
        global $SESS,$JAX;
        if (property_exists($this, 'done') && $this->done !== null) {
            return false;
        }

        $this->done = true;
        $this->parts['path']
            = "<div id='path' class='path'>" . $this->buildpath() . '</div>';

        if ($this->jsaccess) {
            if (!headers_sent()) {
                header('Content-type:text/plain');
            }

            foreach ($this->JSOutput as $k => $v) {
                $this->JSOutput[$k] = $SESS->addSessID($v);
            }

            echo empty($this->JSOutput)
                ? ''
                : $JAX::json_encode($this->JSOutput);
        } else {
            $autobox = ['PAGE', 'COPYRIGHT', 'USERBOX'];
            foreach ($this->parts as $k => $v) {
                $k = mb_strtoupper((string) $k);
                if (in_array($k, $autobox)) {
                    $v = '<div id="' . mb_strtolower($k) . '">' . $v . '</div>';
                }

                if ($k === 'PATH') {
                    $this->template
                        = preg_replace('@<!--PATH-->@', (string) $v, (string) $this->template, 1);
                }

                $this->template = str_replace('<!--' . $k . '-->', $v, $this->template);
            }

            $this->template = $this->filtervars($this->template);
            $this->template = $SESS->addSessId($this->template);
            if ($this->checkextended($this->template, null)) {
                $this->template = $this->metaextended($this->template);
            }

            echo $this->template;
        }

        return null;
    }

    public function collapsebox($a, $b, $c = false): ?string
    {
        return $this->meta('collapsebox', $c ? ' id="' . $c . '"' : '', $a, $b);
    }

    public function error($a): ?string
    {
        return $this->meta('error', $a);
    }

    public function templatehas($a): false|int
    {
        return preg_match("/<!--{$a}-->/i", (string) $this->template);
    }

    public function loadtemplate($a): void
    {
        $this->template = file_get_contents($a);
        $this->template = preg_replace_callback(
            '@<!--INCLUDE:(\w+)-->@',
            $this->includer(...),
            $this->template,
        );
        $this->template = preg_replace_callback(
            '@<M name=([\'"])([^\'"]+)\1>(.*?)</M>@s',
            $this->userMetaParse(...),
            (string) $this->template,
        );
    }

    public function loadskin($id): void
    {
        global $DB,$CFG;
        $skin = [];
        if ($id) {
            $result = $DB->safeselect(
                ['title', 'custom', 'wrapper'],
                'skins',
                'WHERE id=? LIMIT 1',
                $id,
            );
            $skin = $DB->arow($result);
            $DB->disposeresult($result);
        }

        if (empty($skin)) {
            $result = $DB->safeselect(
                ['title', 'custom', 'wrapper'],
                'skins',
                'WHERE `default`=1 LIMIT 1',
            );
            $skin = $DB->arow($result);
            $DB->disposeresult($result);
        }

        if (!$skin) {
            $skin = [
                'custom' => 0,
                'title' => 'Default',
                'wrapper' => false,
            ];
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
            $skin['wrapper']
            ? BOARDPATH . 'Wrappers/' . $skin['wrapper'] . '.txt'
            : THEMEPATH . 'wrappers.txt',
        );
    }

    public function userMetaParse($m): string
    {
        $this->checkextended($m[3], $m[2]);
        $this->userMetaDefs[$m[2]] = $m[3];

        return '';
    }

    public function includer($m)
    {
        global $DB;
        $result = $DB->safeselect(
            'page',
            'pages',
            'WHERE `act`=?',
            $DB->basicvalue($m[1]),
        );
        $page = array_shift($DB->arow($result));
        $DB->disposeresult($result);

        return $page ?: '';
    }

    public function loadmeta($component): void
    {
        $component = mb_strtolower((string) $component);
        $themeComponentDir = THEMEPATH . 'views/' . $component;
        if (is_dir($themeComponentDir)) {
            $componentDir = $themeComponentDir;
        } else {
            $componentDir = DTHEMEPATH . 'views/' . $component;
            if (!is_dir($componentDir)) {
                $componentDir = false;
            }
        }

        if ($componentDir === false) {
            return;
        }

        $this->metaqueue[] = $componentDir;
        $this->debug("Added {$component} to queue");
    }

    public function processqueue($process): void
    {
        while ($componentDir = array_pop($this->metaqueue)) {
            $component = pathinfo((string) $componentDir, PATHINFO_BASENAME);
            $this->debug("{$process} triggered {$component} to load");
            $meta = [];
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
                $componentDir,
            );
            if ($defaultComponentDir !== $componentDir) {
                foreach (glob($defaultComponentDir . '/*.html') as $metaFile) {
                    $metaName = pathinfo($metaFile, PATHINFO_FILENAME);
                    $metaContent = file_get_contents($metaFile);
                    $this->checkextended($metaContent, $metaName);
                    if (isset($meta[$metaName])) {
                        continue;
                    }

                    $meta[$metaName] = $metaContent;
                }
            }

            $this->metadefs = $meta + $this->metadefs;
        }
    }

    public function meta(...$args): ?string
    {
        $meta = array_shift($args);
        $this->processqueue($meta);
        $r = vsprintf(
            str_replace(
                ['<%', '%>'],
                ['<%%', '%%>'],
                $this->userMetaDefs[$meta] ?? $this->metadefs[$meta] ?? '',
            ),
            isset($args[0]) && is_array($args[0]) ? $args[0] : $args,
        );
        if ($r === false) {
            echo $meta . ' has too many arguments';

            exit(1);
        }

        if (
            isset($this->moreFormatting[$meta])
            && $this->moreFormatting[$meta]
        ) {
            return $this->metaextended($r);
        }

        return $r;
    }

    public function metaextended($m): ?string
    {
        return preg_replace_callback(
            '@{if ([^}]+)}(.*){/if}@Us',
            $this->metaextendedifcb(...),
            (string) $this->filtervars($m),
        );
    }

    public function metaextendedifcb($m)
    {
        $s = mb_strpos((string) $m[1], '||') !== false ? '||' : '&&';

        foreach (explode($s, (string) $m[1]) as $piece) {
            preg_match('@(\S+?)\s*([!><]?=|[><])\s*(\S*)@', $piece, $pp);

            switch ($pp[2]) {
                case '=':
                    $c = $pp[1] === $pp[3];

                    break;

                case '!=':
                    $c = $pp[1] !== $pp[3];

                    break;

                case '>=':
                    $c = $pp[1] >= $pp[3];

                    break;

                case '>':
                    $c = $pp[1] > $pp[3];

                    break;

                case '<=':
                    $c = $pp[1] <= $pp[3];

                    break;

                case '<':
                    $c = $pp[1] < $pp[3];

                    break;
            }

            if ($s === '&&' && !$c) {
                break;
            }

            if ($s === '||' && $c) {
                break;
            }
        }

        if ($c) {
            return $m[2];
        }

        return '';
    }

    public function checkextended($data, $meta = null): bool
    {
        if (mb_strpos((string) $data, '{if ') !== false) {
            if (!$meta) {
                return true;
            }

            $this->moreFormatting[$meta] = true;
        }

        return false;
    }

    public function metaexists($meta): bool
    {
        return isset($this->userMetaDefs[$meta])
            || isset($this->metadefs[$meta]);
    }

    public function path($a): bool
    {
        if (
            !isset($this->parts['path'])
            || !is_array($this->parts['path'])
        ) {
            $this->parts['path'] = [];
        }

        foreach ($a as $value => $link) {
            $this->parts['path'][$link] = $value;
        }

        return true;
    }

    public function buildpath(): ?string
    {
        $first = true;
        $path = '';
        foreach ($this->parts['path'] as $value => $link) {
            $path .= $this->meta(
                $first
                && $this->metaexists('path-home') ? 'path-home' : 'path-part',
                $value,
                $link,
            );
            $first = false;
        }

        return $this->meta('path', $path);
    }

    public function updatepath($a = false): void
    {
        if ($a) {
            $this->path($a);
        }

        $this->JS('update', 'path', $this->buildpath());
    }

    public function debug($data = '')
    {
        if (!$data) {
            return $this->debuginfo;
        }

        $this->debuginfo .= $data . '<br />';

        return null;
    }
}
