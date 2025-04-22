<?php

declare(strict_types=1);

namespace ACP\Page;

final class Tools
{
    public function route(): void
    {
        global $JAX,$PAGE;

        $links = [
            'backup' => 'Backup',
            'files' => 'File Manager',
            'errorlog' => 'View Error Log',
        ];

        $sidebarLinks = '';

        foreach ($links as $do => $title) {
            $sidebarLinks .= $PAGE->parseTemplate(
                'sidebar-list-link.html',
                [
                    'title' => $title,
                    'url' => '?act=tools&do=' . $do,
                ],
            ) . PHP_EOL;
        }

        $PAGE->sidebar(
            $PAGE->parseTemplate(
                'sidebar-list.html',
                [
                    'content' => $sidebarLinks,
                ],
            ),
        );

        if (!isset($JAX->b['do'])) {
            $JAX->b['do'] = null;
        }

        match ($JAX->b['do']) {
            'backup' => $this->backup(),
            'errorlog' => $this->errorlog(),
            default => $this->filemanager(),
        };
    }

    public function filemanager(): void
    {
        global $PAGE,$DB,$JAX,$CFG;
        $page = '';
        if (isset($JAX->b['delete']) && is_numeric($JAX->b['delete'])) {
            $result = $DB->safeselect(
                [
                    'hash',
                    'name',
                ],
                'files',
                'WHERE `id`=?',
                $DB->basicvalue($JAX->b['delete']),
            );
            $file = $DB->arow($result);
            $DB->disposeresult($result);
            if ($file) {
                $ext = mb_strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
                    $file['hash'] .= '.' . $ext;
                }

                if (is_writable(BOARDPATH . 'Uploads/' . $file['hash'])) {
                    $page .= unlink(BOARDPATH . 'Uploads/' . $file['hash'])
                        ? $PAGE->success('File deleted')
                        : $PAGE->error(
                            "Error deleting file, maybe it's already been "
                            . 'deleted? Removed from DB',
                        );
                }

                $DB->safedelete(
                    'files',
                    'WHERE `id`=?',
                    $DB->basicvalue($JAX->b['delete']),
                );
            }
        }

        if (isset($JAX->p['dl']) && is_array($JAX->p['dl'])) {
            foreach ($JAX->p['dl'] as $k => $v) {
                if (!ctype_digit((string) $v)) {
                    continue;
                }

                $DB->safeupdate(
                    'files',
                    [
                        'downloads' => $v,
                    ],
                    'WHERE `id`=?',
                    $DB->basicvalue($k),
                );
            }

            $page .= $PAGE->success('Changes saved.');
        }

        $result = $DB->safeselect(
            '`id`,`tid`,`post`',
            'posts',
            "WHERE MATCH (`post`) AGAINST ('attachment') "
            . "AND post LIKE '%[attachment]%'",
        );
        $linkedin = [];
        while ($f = $DB->arow($result)) {
            preg_match_all(
                '@\[attachment\](\d+)\[/attachment\]@',
                (string) $f['post'],
                $m,
            );
            foreach ($m[1] as $v) {
                $linkedin[$v][] = $PAGE->parseTemplate(
                    'tools/attachment-link.html',
                    [
                        'post_id' => $f['id'],
                        'topic_id' => $f['tid'],
                    ],
                );
            }
        }

        $result = $DB->safespecial(
            <<<'EOT'
                SELECT
                    f.`id` AS `id`,
                    f.`name` AS `name`,
                    f.`hash` AS `hash`,
                    f.`uid` AS `uid`,
                    f.`size` AS `size`,
                    f.`downloads` AS `downloads`,
                    m.`display_name` AS `uname`
                FROM %t f
                LEFT JOIN %t m
                    ON f.`uid`=m.`id`
                ORDER BY f.`size` DESC
                EOT
            ,
            ['files', 'members'],
        );
        echo $DB->error(1);
        $table = '';
        while ($file = $DB->arow($result)) {
            $filepieces = explode('.', (string) $file['name']);
            if (count($filepieces) > 1) {
                $ext = mb_strtolower(array_pop($filepieces));
            }

            $file['name'] = in_array($ext, $CFG['images']) ? '<a href="'
                    . BOARDPATHURL . 'Uploads/' . $file['hash'] . '.' . $ext . '">'
                    . $file['name'] . '</a>' : '<a href="../?act=download&id='
                    . $file['id'] . '">' . $file['name'] . '</a>';

            $table .= $PAGE->parseTemplate(
                'tools/file-manager-row.html',
                [
                    'downloads' => $file['downloads'],
                    'filesize' => $JAX->filesize($file['size']),
                    'id' => $file['id'],
                    'linked_in' => isset($linkedin[$file['id']]) && $linkedin[$file['id']]
                        ? implode(', ', $linkedin[$file['id']]) : 'Not linked!',
                    'title' => $file['name'],
                    'username' => $file['uname'],
                    'user_id' => $file['uid'],
                ],
            ) . PHP_EOL;
        }

        $page .= $table !== '' && $table !== '0' ? $PAGE->parseTemplate(
            'tools/file-manager.html',
            [
                'content' => $table,
            ],
        ) : $PAGE->error('No files to show.');
        $PAGE->addContentBox('File Manager', $page);
    }

    public function backup(): void
    {
        global $JAX,$PAGE,$DB;
        if (isset($JAX->p['dl']) && $JAX->p['dl']) {
            header('Content-type: text/plain');
            header(
                'Content-Disposition: attachment;filename="' . $DB->prefix
                . gmdate('Y-m-d_His') . '.sql"',
            );
            $result = $DB->safequery("SHOW TABLES LIKE '{$DB->prefix}%%'");
            $tables = $DB->rows($result);
            $page = '';
            if ($tables) {
                echo PHP_EOL . "-- Jaxboards Backup {$DB->prefix} "
                    . gmdate('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;
                echo 'SET NAMES utf8mb4;' . PHP_EOL;
                echo "SET time_zone = '+00:00';" . PHP_EOL;
                echo 'SET foreign_key_checks = 0;' . PHP_EOL;
                echo "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';" . PHP_EOL;
                echo PHP_EOL;
                foreach ($tables as $f) {
                    $f[0] = mb_substr(mb_strstr((string) $f[0], '_'), 1);
                    $page .= $f[0];
                    echo PHP_EOL . '-- ' . $f[0] . PHP_EOL . PHP_EOL;
                    $createtable = $DB->safespecial(
                        'SHOW CREATE TABLE %t',
                        [$f[0]],
                    );
                    $thisrow = $DB->row($createtable);
                    if (!$thisrow) {
                        continue;
                    }

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
                        echo "INSERT INTO {$table} ({$columns}) "
                            . "VALUES {$values};" . PHP_EOL;
                    }

                    echo PHP_EOL;
                }

                echo PHP_EOL;
                echo 'SET foreign_key_checks = 1;' . PHP_EOL;
                echo PHP_EOL;
            }

            exit;
        }

        $PAGE->addContentBox(
            'Backup Forum',
            $PAGE->parseTemplate(
                'tools/backup.html',
            ),
        );
    }

    public function errorlog(): void
    {
        global $PAGE;

        $logPath = ini_get('error_log');

        $contents = "Sorry, Jaxboards does not have file permissions to read your PHP error log file. ({$logPath})";

        if (is_readable($logPath)) {
            $last100Lines = htmlspecialchars(implode(PHP_EOL, $this->tail(
                $logPath,
                100,
            )));
            $contents = <<<HTML
                <label for="errorlog">
                    Recent PHP error log output
                 </label>
                <textarea
                    id="errorlog"
                    class="editor"
                    disabled="disabled"
                    >{$last100Lines}</textarea>

                HTML;
        }

        $PAGE->addContentBox(
            "PHP Error Log ({$logPath})",
            $contents,
        );
    }

    // Reads the last $totalLines of a file
    public function tail($path, $totalLines): array
    {
        $logFile = new SplFileObject($path, 'r');
        $logFile->fseek(0, SEEK_END);

        $lines = [];
        $lastLine = '';

        // Loop backward until we have our lines or we reach the start
        for ($pos = $logFile->ftell() - 1; $pos >= 0; --$pos) {
            $logFile->fseek($pos);
            $character = $logFile->fgetc();

            if ($pos === 0 || $character !== "\n") {
                $lastLine = $character . $lastLine;
            }

            if ($pos !== 0 && $character !== "\n") {
                continue;
            }

            // skip empty lines
            if (trim($lastLine) === '') {
                continue;
            }

            $lines[] = $lastLine;
            $lastLine = '';

            if (count($lines) >= $totalLines) {
                break;
            }
        }

        return array_reverse($lines);
    }
}
