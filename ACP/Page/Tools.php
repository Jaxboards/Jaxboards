<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use Jax\Config;
use Jax\Database;
use Jax\DomainDefinitions;
use Jax\Jax;
use Jax\Request;
use SplFileObject;

use function array_map;
use function array_pop;
use function array_reverse;
use function array_values;
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
        private readonly DomainDefinitions $domainDefinitions,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Request $request,
    ) {}

    public function render(): void
    {
        $this->page->sidebar([
            'backup' => 'Backup',
            'files' => 'File Manager',
            'viewErrorLog' => 'View Error Log',
        ]);

        match ($this->request->both('do')) {
            'backup' => $this->backup(),
            'viewErrorLog' => $this->viewErrorLog(),
            default => $this->filemanager(),
        };
    }

    private function filemanager(): void
    {
        $page = '';
        if (
            is_numeric($this->request->both('delete'))
        ) {
            $result = $this->database->safeselect(
                [
                    'hash',
                    'name',
                ],
                'files',
                'WHERE `id`=?',
                $this->database->basicvalue($this->request->both('delete')),
            );
            $file = $this->database->arow($result);
            $this->database->disposeresult($result);
            if ($file) {
                $ext = mb_strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
                    $file['hash'] .= '.' . $ext;
                }

                if (is_writable($this->domainDefinitions->getBoardPath() . '/Uploads/' . $file['hash'])) {
                    $page .= unlink($this->domainDefinitions->getBoardPath() . '/Uploads/' . $file['hash'])
                        ? $this->page->success('File deleted')
                        : $this->page->error(
                            "Error deleting file, maybe it's already been "
                            . 'deleted? Removed from DB',
                        );
                }

                $this->database->safedelete(
                    'files',
                    'WHERE `id`=?',
                    $this->database->basicvalue($this->request->both('delete')),
                );
            }
        }

        if (is_array($this->request->post('dl'))) {
            foreach ($this->request->post('dl') as $k => $v) {
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
            <<<'SQL'
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
                SQL
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
                    . $this->domainDefinitions->getBoardPathUrl() . 'Uploads/' . $file['hash'] . '.' . $ext . '">'
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

    private function backup(): void
    {
        if ($this->request->post('dl') !== null) {
            header('Content-type: text/plain');
            header(
                'Content-Disposition: attachment;filename="' . $this->database->getPrefix()
                . gmdate('Y-m-d_His') . '.sql"',
            );
            $result = $this->database->safequery("SHOW TABLES LIKE '{$this->database->getPrefix()}%%'");
            $tables = array_map(static fn(array $row) => array_values($row)[0], $this->database->arows($result));
            $page = '';
            if ($tables !== []) {
                echo PHP_EOL . "-- Jaxboards Backup {$this->database->getPrefix()} "
                    . $this->database->datetime() . PHP_EOL . PHP_EOL;
                echo 'SET NAMES utf8mb4;' . PHP_EOL;
                echo "SET time_zone = '+00:00';" . PHP_EOL;
                echo 'SET foreign_key_checks = 0;' . PHP_EOL;
                echo "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';" . PHP_EOL;
                echo PHP_EOL;
                foreach ($tables as $table) {
                    $table = mb_substr(mb_strstr((string) $table, '_'), 1);
                    $page .= $table;
                    echo PHP_EOL . '-- ' . $table . PHP_EOL . PHP_EOL;
                    $createtable = $this->database->safespecial(
                        'SHOW CREATE TABLE %t',
                        [$table],
                    );
                    $thisrow = $this->database->row($createtable);
                    if (!$thisrow) {
                        continue;
                    }

                    $ftable = $this->database->ftable($table);
                    echo "DROP TABLE IF EXISTS {$ftable};" . PHP_EOL;
                    echo array_pop($thisrow) . ';' . PHP_EOL;
                    $this->database->disposeresult($createtable);
                    // Only time I really want to use *.
                    $select = $this->database->safeselect('*', $table);
                    while ($row = $this->database->arow($select)) {
                        echo $this->database->buildInsertQuery($ftable, $row) . PHP_EOL;
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

    private function viewErrorLog(): void
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
    private function tail(bool|string $path, int $totalLines): array
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
