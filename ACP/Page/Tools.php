<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use ACP\Page\Tools\FileManager;
use Jax\Database;
use Jax\FileUtils;
use Jax\Request;
use ZipArchive;

use function array_map;
use function array_values;
use function class_exists;
use function gmdate;
use function header;
use function htmlspecialchars;
use function implode;
use function ini_get;
use function is_readable;
use function mb_strlen;
use function mb_substr;
use function readfile;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const PHP_EOL;

final readonly class Tools
{
    public function __construct(
        private readonly Database $database,
        private readonly FileUtils $fileUtils,
        private readonly Page $page,
        private readonly Request $request,
        private readonly FileManager $fileManager,
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
            default => $this->fileManager->render(),
        };
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
        $tables = array_map(static fn(array $row): string => (string) array_values($row)[0], $this->database->arows($result));

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

        $zipArchive = new ZipArchive();
        $zipArchive->open($tempFile, ZipArchive::OVERWRITE);
        $zipArchive->addFromString('backup.sql', $fileContents);
        $zipArchive->close();

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
            $last100Lines = htmlspecialchars(implode(PHP_EOL, $this->fileUtils->tail(
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
}
