<?php

if (!defined(INACP)) {
    die();
}

new tools();
class tools
{
    public function __construct()
    {
        global $JAX,$PAGE;
        $sidebar = '';
        $menu = array(
            '?act=tools&do=files' => 'File Manager',
            '?act=tools&do=backup' => 'Backup',
        );
        foreach ($menu as $k => $v) {
            $sidebar .= '<li><a href="' . $k . '">' . $v . '</a></li>';
        }
        $PAGE->sidebar('<ul>' . $sidebar . '</ul>');
        $do = isset($JAX->b['do']) ? $JAX->b['do'] : '';
        switch ($do) {
            case 'files':
                $this->filemanager();
                break;
            case 'backup':
                $this->backup();
                break;
        }
    }

    public function filemanager()
    {
        global $PAGE,$DB,$JAX,$CFG;
        $page = '';
        if (is_numeric(@$JAX->b['delete'])) {
            $result = $DB->safeselect(
                <<<'EOT'
`id`,`name`,`hash`,`uid`,`size`,`downloads`,INET6_NTOA(`ip`) AS `ip`
EOT
                ,
                'files',
                'WHERE `id`=?',
                $DB->basicvalue($JAX->b['delete'])
            );
            $f = $DB->arow($result);
            $DB->disposeresult($result);
            if ($f) {
                $ext = mb_strtolower(array_pop(explode('.', $f['name'])));
                if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'bmp'))) {
                    $f['hash'] .= '.' . $ext;
                }
                $page .= @unlink(BOARDPATH . 'Uploads/' . $f['hash']) ?
                    $PAGE->success('File deleted') :
                    $PAGE->error(
                        'Error deleting file, maybe it\'s already been ' .
                        'deleted? Removed from DB'
                    );
                $DB->safedelete(
                    'files',
                    'WHERE `id`=?',
                    $DB->basicvalue($JAX->b['delete'])
                );
            }
        }
        if (is_array(@$JAX->p['dl'])) {
            foreach ($JAX->p['dl'] as $k => $v) {
                if (ctype_digit($v)) {
                    $DB->safeupdate(
                        'files',
                        array(
                            'downloads' => $v,
                        ),
                        'WHERE `id`=?',
                        $DB->basicvalue($k)
                    );
                }
            }
            $page .= $PAGE->success('Changes saved.');
        }
        $result = $DB->safeselect(
            '`id`,`tid`,`post`',
            'posts',
            "WHERE MATCH (`post`) AGAINST ('attachment') " .
            "AND post LIKE '%[attachment]%'"
        );
        $linkedin = array();
        while ($f = $DB->arow($result)) {
            preg_match_all(
                '@\\[attachment\\](\\d+)\\[/attachment\\]@',
                $f['post'],
                $m
            );
            foreach ($m[1] as $v) {
                $linkedin[$v][] = '<a href="../?act=vt' . $f['tid'] .
                    '&findpost=' . $f['id'] . '">' . $f['id'] . '</a>';
            }
        }
        $result = $DB->safespecial(
            <<<'EOT'
SELECT f.`id` AS `id`,f.`name` AS `name`,f.`hash` AS `hash`,f.`uid` AS `uid`,
    f.`size` AS `size`,f.`downloads` AS `downloads`,INET6_NTOA(f.`ip`) AS `ip`,
    m.`display_name` AS `uname`
FROM %t f
LEFT JOIN %t m
    ON f.`uid`=m.`id`
ORDER BY f.`size` DESC
EOT
            ,
            array('files', 'members')
        );
        echo $DB->error(1);
        $table = '';
        while ($file = $DB->arow($result)) {
            $filepieces = explode('.', $file['name']);
            if (count($filepieces) > 1) {
                $ext = mb_strtolower(array_pop($filepieces));
            }
            if (in_array($ext, $CFG['images'])) {
                $file['name'] = '<a href="' .
                    BOARDPATHURL . 'Uploads/' . $file['hash'] . '.' . $ext . '">' .
                    $file['name'] . '</a>';
            } else {
                $file['name'] = '<a href="../?act=download&id=' .
                    $file['id'] . '">' . $file['name'] . '</a>';
            }
            $table .= '<tr><td>' . $file['name'] . '</td><td>' . $file['id'] .
                '</td><td>' . $JAX->filesize($file['size']) .
                "</td><td align='center'><input type='text' " .
                "style='text-align:center;width:40px' name='dl[" . $file['id'] .
                ']\' value="' . $file['downloads'] . '" /></td><td>' .
                '<a href="../?act=vu' . $file['uid'] . '">' . $file['uname'] .
                '</a></td><td>' . ($linkedin[$file['id']] ?
                implode(', ', $linkedin[$file['id']]) : 'Not linked!') .
                "</td><td align='center'><a onclick='return " .
                "confirm(\"You sure?\")' href='?act=tools&do=files&delete=" .
                $file['id'] . "' class='icons delete'></a></td></tr>";
        }
        $page .= $table ? "<form method='post'><table id='files'><tr><th>" .
            'Filename</th><th>ID</th><th>Size</th><th>Downloads</th><th>' .
            'Uploader</th><th>Linked in</th><th>Delete</th></tr>' . $table .
            "<tr><td colspan='3'></td><td><input type='submit' value='Save'" .
            " /></td><td colspan='3' /></td></table>" :
            $PAGE->error('No files to show.');
        $PAGE->addContentBox('File Manager', $page);
    }

    public function backup()
    {
        global $PAGE,$DB;
        if (@$_POST['dl']) {
            header('Content-type: text/plain');
            header(
                'Content-Disposition: attachment;filename="' . $DB->prefix .
                date('Y-m-d_His') . '.sql"'
            );
            $result = $DB->safequery("SHOW TABLES LIKE '{$DB->prefix}%%'");
            $tables = $DB->rows($result);
            $page = '';
            if ($tables) {
                echo PHP_EOL . "-- Jaxboards Backup {$DB->prefix} " .
                    date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;
                echo 'SET NAMES utf8mb4;' . PHP_EOL;
                echo "SET time_zone = '+00:00';" . PHP_EOL;
                echo 'SET foreign_key_checks = 0;' . PHP_EOL;
                echo "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';" . PHP_EOL;
                echo PHP_EOL;
                foreach ($tables as $f) {
                    $f[0] = mb_substr(mb_strstr($f[0], '_'), 1);
                    $page .= $f[0];
                    echo PHP_EOL . '-- ' . $f[0] . PHP_EOL . PHP_EOL;
                    $createtable = $DB->safespecial(
                        'SHOW CREATE TABLE %t',
                        array($f[0])
                    );
                    $thisrow = $DB->row($createtable);
                    if ($thisrow) {
                        $table = $DB->ftable($f[0]);
                        echo "DROP TABLE IF EXISTS {$table};" . PHP_EOL;
                        echo array_pop($thisrow) . ';' . PHP_EOL;
                        $DB->disposeresult($createtable);
                        // Only time I really want to use *.
                        $select = $DB->safeselect('*', $f[0]);
                        while ($row = $DB->arow($select)) {
                            $insert = $DB->buildInsert($row);
                            $columns = $insert[0];
                            $values = $insert[1];
                            echo "INSERT INTO {$table} ({$columns}) " .
                                "VALUES {$values};" . PHP_EOL;
                        }
                        echo PHP_EOL;
                    }
                }
                echo PHP_EOL;
                echo 'SET foreign_key_checks = 1;' . PHP_EOL;
                echo PHP_EOL;
            }
            die();
        }
        $PAGE->addContentBox(
            'Backup Forum',
            <<<'EOT'
This tool will allow you to download and save a backup of your forum in case
something happens.
<br /><br />
    <form method='post' onsubmit='this.submit.disabled=true'>
    <input type='hidden' name='dl' value='1' />
    <input type='submit' name='submit' value='Download Backup'
    onmouseup='this.value=\"Generating backup...\";' />
    </form>
EOT
        );
    }
}
