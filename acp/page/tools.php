<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\Jax;
use SplFileObject;

use function array_pop;
use function array_reverse;
use function count;
use function ctype_digit;
use function explode;
use function gmdate;
use function header;
use function htmlspecialchars;
use function implode;
use function in_array;
use function ini_get;
use function is_array;
use function is_numeric;
use function is_readable;
use function is_writable;
use function mb_strstr;
use function mb_strtolower;
use function mb_substr;
use function pathinfo;
use function preg_match_all;
use function trim;
use function unlink;

use const PATHINFO_EXTENSION;
use const PHP_EOL;
use const SEEK_END;

final readonly class Tools
{
    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly Page $page,
        private readonly Jax $jax,
    ) {}

    public function route(): void
    {
        $links = [
            'backup' => 'Backup',
            'files' => 'File Manager',
            'errorlog' => 'View Error Log',
        ];

        $sidebarLinks = '';

        foreach ($links as $do => $title) {
            $sidebarLinks .= $this->page->parseTemplate(
                'sidebar-list-link.html',
                [
                    'title' => $title,
                    'url' => '?act=tools&do=' . $do,
                ],
            ) . PHP_EOL;
        }

        $this->page->sidebar(
            $this->page->parseTemplate(
                'sidebar-list.html',
                [
                    'content' => $sidebarLinks,
                ],
            ),
        );

        if (!isset($this->jax->b['do'])) {
            $this->jax->b['do'] = null;
        }

        match ($this->jax->b['do']) {
            'backup' => $this->backup(),
            'errorlog' => $this->errorlog(),
            default => $this->filemanager(),
        };
    }

    public function filemanager(): void
    {
        $page = '';
        if (
            isset($this->jax->b['delete'])
            && is_numeric($this->jax->b['delete'])
        ) {
            $result = $this->database->safeselect(
                [
                    'hash',
                    'name',
                ],
                'files',
                'WHERE `id`=?',
                $this->database->basicvalue($this->jax->b['delete']),
            );
            $file = $this->database->arow($result);
            $this->database->disposeresult($result);
            if ($file) {
                $ext = mb_strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
                    $file['hash'] .= '.' . $ext;
                }

                if (is_writable(BOARDPATH . 'Uploads/' . $file['hash'])) {
                    $page .= unlink(BOARDPATH . 'Uploads/' . $file['hash'])
                        ? $this->page->success('File deleted')
                        : $this->page->error(
                            "Error deleting file, maybe it's already been "
                            . 'deleted? Removed from DB',
                        );
                }

                $this->database->safedelete(
                    'files',
                    'WHERE `id`=?',
                    $this->database->basicvalue($this->jax->b['delete']),
                );
            }
        }

        if (isset($this->jax->p['dl']) && is_array($this->jax->p['dl'])) {
            foreach ($this->jax->p['dl'] as $k => $v) {
                if (!ctype_digit((string) $v)) {
                    continue;
                }

                $this->database->safeupdate(
                    'files',
                    [
                        'downloads' => $v,
                    ],
                    'WHERE `id`=?',
                    $this->database->basicvalue($k),
                );
            }

            $page .= $this->page->success('Changes saved.');
        }

        $result = $this->database->safeselect(
            '`id`,`tid`,`post`',
            'posts',
            "WHERE MATCH (`post`) AGAINST ('attachment') "
            . "AND post LIKE '%[attachment]%'",
        );
        $linkedin = [];
        while ($f = $this->database->arow($result)) {
            preg_match_all(
                '@\[attachment\](\d+)\[/attachment\]@',
                (string) $f['post'],
                $m,
            );
            foreach ($m[1] as $v) {
                $linkedin[$v][] = $this->page->parseTemplate(
                    'tools/attachment-link.html',
                    [
                        'post_id' => $f['id'],
                        'topic_id' => $f['tid'],
                    ],
                );
            }
        }

        $result = $this->database->safespecial(
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
        echo $this->database->error();
        $table = '';
        while ($file = $this->database->arow($result)) {
            $filepieces = explode('.', (string) $file['name']);
            if (count($filepieces) > 1) {
                $ext = mb_strtolower(array_pop($filepieces));
            }

            $file['name'] = in_array($ext, $this->config->getSetting('images')) ? '<a href="'
                    . BOARDPATHURL . 'Uploads/' . $file['hash'] . '.' . $ext . '">'
                    . $file['name'] . '</a>' : '<a href="../?act=download&id='
                    . $file['id'] . '">' . $file['name'] . '</a>';

            $table .= $this->page->parseTemplate(
                'tools/file-manager-row.html',
                [
                    'downloads' => $file['downloads'],
                    'filesize' => $this->jax->filesize($file['size']),
                    'id' => $file['id'],
                    'linked_in' => isset($linkedin[$file['id']]) && $linkedin[$file['id']]
                        ? implode(', ', $linkedin[$file['id']]) : 'Not linked!',
                    'title' => $file['name'],
                    'username' => $file['uname'],
                    'user_id' => $file['uid'],
                ],
            ) . PHP_EOL;
        }

        $page .= $table !== '' && $table !== '0' ? $this->page->parseTemplate(
            'tools/file-manager.html',
            [
                'content' => $table,
            ],
        ) : $this->page->error('No files to show.');
        $this->page->addContentBox('File Manager', $page);
    }

    public function backup(): void
    {
        if (isset($this->jax->p['dl']) && $this->jax->p['dl']) {
            header('Content-type: text/plain');
            header(
                'Content-Disposition: attachment;filename="' . $this->database->getPrefix()
                . gmdate('Y-m-d_His') . '.sql"',
            );
            $result = $this->database->safequery("SHOW TABLES LIKE '{$this->database->getPrefix()}%%'");
            $tables = $this->database->rows($result);
            $page = '';
            if ($tables) {
                echo PHP_EOL . "-- Jaxboards Backup {$this->database->getPrefix()} "
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
                    $createtable = $this->database->safespecial(
                        'SHOW CREATE TABLE %t',
                        [$f[0]],
                    );
                    $thisrow = $this->database->row($createtable);
                    if (!$thisrow) {
                        continue;
                    }

                    $table = $this->database->ftable($f[0]);
                    echo "DROP TABLE IF EXISTS {$table};" . PHP_EOL;
                    echo array_pop($thisrow) . ';' . PHP_EOL;
                    $this->database->disposeresult($createtable);
                    // Only time I really want to use *.
                    $select = $this->database->safeselect('*', $f[0]);
                    while ($row = $this->database->arow($select)) {
                        $insert = $this->database->buildInsert($row);
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

        $this->page->addContentBox(
            'Backup Forum',
            $this->page->parseTemplate(
                'tools/backup.html',
            ),
        );
    }

    public function errorlog(): void
    {
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

        $this->page->addContentBox(
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
