<?php

declare(strict_types=1);

namespace Tools;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Jax\Database\Database;
use Jax\Database\Utils;
use Jax\DebugLog;
use Jax\FileSystem;
use Override;
use PDOException;
use Tools\Migrations\Migration;

use function array_reduce;
use function implode;
use function ksort;
use function preg_match;

use const PHP_EOL;

/**
 * Updates a database from one version to another.
 *
 * Usage:
 * - `migrate`: Attempts to the database schema to the latest version.
 * - `migrate create $modelName`: Displays create table statement for model
 */
final readonly class Migrations implements CLIRoute
{
    public function __construct(
        private Console $console,
        private Container $container,
        private DebugLog $debugLog,
        private Database $database,
        private FileSystem $fileSystem,
        private Utils $utils,
    ) {}

    public function get_db_version(): int
    {
        $statsResult = $this->database->select('*', 'stats');

        /** @var array{dbVersion:?int} $statsRow */
        $statsRow = $this->database->arow($statsResult);

        return $statsRow['dbVersion'] ?? 0;
    }

    /**
     * @param array<string,string> $params
     * @throws DependencyException
     * @throws NotFoundException
     */
    #[Override]
    public function route(array $params): void
    {
        $command = $params[0] ?? '';

        match ($command) {
            'create' => $this->generate($params[1] ?? ''),
            default => $this->run_migrations(),
        };
    }

    private function generate($modelName)
    {
        try {
            $model = $this->container->get("Jax\\Models\\{$modelName}");
            $this->console->log($this->utils->createTableQueryFromModel($model));
        } catch (NotFoundException $e) {
            $this->console->error('Cannot find model: ' . $modelName);
        }
    }

    private function run_migrations(): void
    {
        /** @var array<int,string> $migrations */
        $migrations = array_reduce(
            $this->fileSystem->glob('Tools/Migrations/**/*.php'),
            /**
             * @param array<string> $migrations
             * @return array<string>
             */
            function (array $migrations, string $path): array {
                $match = [];
                preg_match('/V(\d+)/', $path, $match);
                if (array_key_exists(1, $match)) {
                    $fileInfo = $this->fileSystem->getFileInfo($path);
                    $migrations[(int) $match[1]] = $fileInfo->getBasename('.' . $fileInfo->getExtension());
                }

                return $migrations;
            },
            [],
        );

        // Sort migrations to run them in order
        ksort($migrations);

        $dbVersion = $this->get_db_version();

        foreach ($migrations as $version => $migration) {
            if ($version <= $dbVersion) {
                continue;
            }

            $this->console->notice("migrating from v{$dbVersion} to v{$version}");

            /** @var Migration $migrationClass */
            $migrationClass = $this->container->get("Tools\\Migrations\\V{$version}\\{$migration}");

            try {
                $migrationClass->execute();
            } catch (PDOException $e) {
                $this->console->error("Error updating to V{$version}: {$e->getMessage()}");
                exit(1);
            }

            // Update DB version
            $this->database->update('stats', ['dbVersion' => $version]);
        }

        $this->console->log(implode(PHP_EOL, $this->debugLog->getLog()));

        $this->console->success('You are currently up to date! DB Version: ' . $this->get_db_version());
    }
}
