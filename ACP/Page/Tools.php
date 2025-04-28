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
use ZipArchive;

use function array_map;
use function array_pop;
use function array_reverse;
use function array_values;
use function class_exists;
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
use function Jax\FileUtils\fileSizeHumanReadable;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function pathinfo;
use function preg_match_all;
use function readfile;
use function sys_get_temp_dir;
use function tempnam;
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
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'], true)) {
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
        while ($post = $this->database->arow($result)) {
            preg_match_all(
                '@\[attachment\](\d+)\[/attachment\]@',
                (string) $post['post'],
                $matches,
            );
            foreach ($matches[1] as $attachmentId) {
                $linkedin[$attachmentId][] = $this->page->parseTemplate(
                    'tools/attachment-link.html',
                    [
                        'post_id' => $post['id'],
                        'topic_id' => $post['tid'],
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

            $file['name'] = in_array($ext, $this->config->getSetting('images'), true) ? '<a href="'
                    . $this->domainDefinitions->getBoardPathUrl() . 'Uploads/' . $file['hash'] . '.' . $ext . '">'
                    . $file['name'] . '</a>' : '<a href="../?act=download&id='
                    . $file['id'] . '">' . $file['name'] . '</a>';

            $table .= $this->page->parseTemplate(
                'tools/file-manager-row.html',
                [
                    'downloads' => $file['downloads'],
                    'filesize' => fileSizeHumanReadable($file['size']),
                    'id' => $file['id'],
                    'linked_in' => isset($linkedin[$file['id']]) && $linkedin[$file['id']]
                        ? implode(', ', $linkedin[$file['id']]) : 'Not linked!',
                    'title' => $file['name'],
                    'username' => $file['uname'],
                    'user_id' => $file['uid'],
                ],
            );
        }

        $page .= $table !== '' ? $this->page->parseTemplate(
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
            $this->createForumBackup();
        }

        $this->page->addContentBox(
            'Backup Forum',
            $this->page->parseTemplate(
                'tools/backup.html',
            ),
        );
    }

    private function createForumBackup(): void
    {
        $dbPrefix = $this->database->getPrefix();

        $result = $this->database->safequery("SHOW TABLES LIKE '{$dbPrefix}%%'");
        $tables = array_map(static fn(array $row) => (string) array_values($row)[0], $this->database->arows($result));

        $sqlFileLines = [
            "-- Jaxboards Backup {$dbPrefix} {$this->database->datetime()}",
            '',
            'SET NAMES utf8mb4;',
            "SET time_zone = '+00:00';",
            'SET foreign_key_checks = 0;',
            "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';",
            '',
        ];

        foreach ($tables as $table) {
            $table = mb_substr($table, mb_strlen($dbPrefix));
            $sqlFileLines[] = '-- ' . $table;
            $sqlFileLines[] = '';

            $result = $this->database->safespecial(
                'SHOW CREATE TABLE %t',
                [$table],
            );
            $createTable = $this->database->arow($result)['Create Table'];
            $this->database->disposeresult($result);

            $ftable = $this->database->ftable($table);
            $sqlFileLines[] = "DROP TABLE IF EXISTS {$ftable};";
            $sqlFileLines[] = "{$createTable};";

            // Generate INSERTS with all row data
            $select = $this->database->safeselect('*', $table);
            while ($row = $this->database->arow($select)) {
                $sqlFileLines[] = $this->database->buildInsertQuery($ftable, $row);
            }
            $sqlFileLines[] = '';
        }

        $sqlFileLines[] = 'SET foreign_key_checks = 1;';

        if (class_exists(ZipArchive::class, false)) {
            $this->outputZipFile(implode(PHP_EOL, $sqlFileLines));

            exit;
        }

        $this->outputTextFile(implode(PHP_EOL, $sqlFileLines));

        exit;
    }

    private function outputZipFile(string $fileContents): void
    {
        header('Content-type: application/zip');
        header(
            'Content-Disposition: attachment;filename="' . $this->database->getPrefix()
            . gmdate('Y-m-d_His') . '.zip"',
        );

        $tempFile = tempnam(sys_get_temp_dir(), $this->database->getPrefix());

        $zipFile = new ZipArchive();
        $zipFile->open($tempFile, ZipArchive::OVERWRITE);
        $zipFile->addFromString('backup.sql', $fileContents);
        $zipFile->close();

        readfile($tempFile);
        unlink($tempFile);
    }

    private function outputTextFile(string $fileContents): void
    {
        header('Content-type: text/plain');
        header(
            'Content-Disposition: attachment;filename="' . $this->database->getPrefix()
            . gmdate('Y-m-d_His') . '.sql"',
        );

        echo $fileContents;
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

    /**
     * Reads the last $totalLines of a file.
     *
     * @return array<string>
     */
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
