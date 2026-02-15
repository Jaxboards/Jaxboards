<?php

declare(strict_types=1);

namespace Tools;

use DI\Container;
use Jax\Database\Database;
use Jax\DebugLog;
use Jax\FileSystem;
use PDOException;
use Tools\Migrations\Migration;

use function array_reduce;
use function dirname;
use function implode;
use function ksort;
use function preg_match;

use const PHP_EOL;

require_once dirname(__DIR__) . '/vendor/autoload.php';
$container = new Container();

/** @var FileSystem $fileSystem */
$fileSystem = $container->get(FileSystem::class);

function error(string $message): string
{
    return "\033[31m{$message}\033[0m";
}

function success(string $message): string
{
    return "\033[32m{$message}\033[0m";
}

function get_db_version(Database $database): int
{
    $statsResult = $database->select('*', 'stats');

    /** @var array{dbVersion:?int} $statsRow */
    $statsRow = $database->arow($statsResult);

    return $statsRow['dbVersion'] ?? 0;
}

/** @var array<int,string> $migrations */
$migrations = array_reduce(
    $fileSystem->glob('Tools/Migrations/**/*.php'),
    /**
     * @param array<string> $migrations
     * @return array<string>
     */
    static function (array $migrations, string $path) use ($fileSystem): array {
        $match = [];
        preg_match('/V(\d+)/', $path, $match);
        if (array_key_exists(1, $match)) {
            $fileInfo = $fileSystem->getFileInfo($path);
            $migrations[(int) $match[1]] = $fileInfo->getBasename('.' . $fileInfo->getExtension());
        }

        return $migrations;
    },
    [],
);

// Sort migrations to run them in order
ksort($migrations);

/** @var Database $database */
$database = $container->get(Database::class);

$dbVersion = get_db_version($database);

foreach ($migrations as $version => $migration) {
    if ($version <= $dbVersion) {
        continue;
    }

    echo "notice: migrating from v{$dbVersion} to v{$version}" . PHP_EOL;

    /** @var Migration $migrationClass */
    $migrationClass = $container->get("Tools\\Migrations\\V{$version}\\{$migration}");

    try {
        $migrationClass->execute($database);
    } catch (PDOException $e) {
        echo error("Error updating to V{$version}: {$e->getMessage()}") . PHP_EOL;

        exit();
    }

    // Update DB version
    $database->update('stats', ['dbVersion' => $version]);
}

/** @var DebugLog $debugLog */
$debugLog = $container->get(DebugLog::class);

echo implode(PHP_EOL, $debugLog->getLog());

echo success('You are currently up to date! DB Version: ' . get_db_version($database)) . PHP_EOL;
