<?php

declare(strict_types=1);

namespace Tools;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Jax\Database\Database;
use Jax\DebugLog;
use Jax\FileSystem;
use Override;
use PDOException;
use Tools\Migrations\Migration;

use function array_reduce;
use function dirname;
use function implode;
use function ksort;
use function preg_match;

use const PHP_EOL;

require_once dirname(__DIR__) . '/vendor/autoload.php';

final readonly class Migrations implements CLIRoute
{
    public function __construct(
        private Container $container,
        private DebugLog $debugLog,
        private Database $database,
        private FileSystem $fileSystem,
    ) {}

    public function error(string $message): string
    {
        return "\033[31m{$message}\033[0m";
    }

    public function success(string $message): string
    {
        return "\033[32m{$message}\033[0m";
    }

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

            echo "notice: migrating from v{$dbVersion} to v{$version}" . PHP_EOL;

            /** @var Migration $migrationClass */
            $migrationClass = $this->container->get("Tools\\Migrations\\V{$version}\\{$migration}");

            try {
                $migrationClass->execute();
            } catch (PDOException $e) {
                echo $this->error("Error updating to V{$version}: {$e->getMessage()}") . PHP_EOL;

                exit();
            }

            // Update DB version
            $this->database->update('stats', ['dbVersion' => $version]);
        }

        echo implode(PHP_EOL, $this->debugLog->getLog());

        echo $this->success('You are currently up to date! DB Version: ' . $this->get_db_version()) . PHP_EOL;
    }
}
