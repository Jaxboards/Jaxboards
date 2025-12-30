<?php

declare(strict_types=1);

namespace ACP\Page;

use ACP\Page;
use ACP\Page\Tools\FileManager;
use Jax\Database\Database;
use Jax\Database\Utils as DatabaseUtils;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\Request;
use ZipArchive;

use function class_exists;
use function gmdate;
use function header;
use function htmlspecialchars;
use function implode;
use function ini_get;
use function readfile;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const GLOB_BRACE;
use const PHP_EOL;

final readonly class Tools
{
    public function __construct(
        private Database $database,
        private DatabaseUtils $databaseUtils,
        private DomainDefinitions $domainDefinitions,
        private FileSystem $fileSystem,
        private Page $page,
        private Request $request,
        private FileManager $fileManager,
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

        $models = $this->databaseUtils->getModels();

        $sqlFileLines = [
            "-- Jaxboards Backup {$dbPrefix} {$this->database->datetime()}",
            '',
            'SET NAMES utf8mb4;',
            "SET time_zone = '+00:00';",
            'SET foreign_key_checks = 0;',
            '',
        ];

        foreach ($models as $model) {
            $tableName = $model::TABLE;
            $sqlFileLines[] = '-- ' . $tableName;
            $sqlFileLines[] = '';

            $ftable = $this->database->ftable($tableName);
            $sqlFileLines[] = "DROP TABLE IF EXISTS {$ftable};";
            $sqlFileLines[] = $this->databaseUtils->createTableQueryFromModel(new $model()) . ';';

            // Generate INSERTS with all row data
            $select = $this->database->select('*', $tableName);
            foreach ($this->database->arows($select) as $row) {
                $sqlFileLines[] = $this->databaseUtils->buildInsertQuery($ftable, [$row]);
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

        $boardPath = $this->fileSystem->pathFromRoot($this->domainDefinitions->getBoardPath());

        $zipArchive = new ZipArchive();
        $zipArchive->open($tempFile, ZipArchive::OVERWRITE);

        $globStarStarIsNotSupportedWTF = implode(',', [
            '',
            '/*',
            '/*/*',
            '/*/*/*',
            '/*/*/*/*',
            '/*/*/*/*/*',
            '/*/*/*/*/*/*',
        ]);

        $zipArchive->addGlob($boardPath . "{{$globStarStarIsNotSupportedWTF}}/*.*", GLOB_BRACE, ['remove_path' => $boardPath]);
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
        $logPath = ini_get('error_log') ?: '';

        $contents = "Sorry, Jaxboards does not have file permissions to read your PHP error log file. ({$logPath})";

        if ($this->fileSystem->getFileInfo($logPath, true)->isReadable()) {
            $logFile = $this->fileSystem->getFileObject($logPath, 'r', true);

            $last100Lines = htmlspecialchars(implode(PHP_EOL, $this->fileSystem->tail(
                $logFile,
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
