<?php

declare(strict_types=1);

namespace tools;

use DI\Container;
use Jax\Database;
use Jax\DebugLog;
use PDOException;

use function array_reduce;
use function dirname;
use function glob;
use function implode;
use function ksort;
use function pathinfo;
use function preg_match;

use const PATHINFO_FILENAME;
use const PHP_EOL;

$jaxboardsRoot = dirname(__DIR__);

require_once dirname(__DIR__) . '/vendor/autoload.php';

function error(string $message): string
{
    return "\033[31m{$message}\033[0m";
}

function success(string $message): string
{
    return "\033[32m{$message}\033[0m";
}

function getDBVersion(Database $database): int
{
    $statsResult = $database->select('*', 'stats');
    $statsRow = $database->arow($statsResult);

    return $statsRow['dbVersion'] ?? 0;
}

$migrations = array_reduce(
    glob($jaxboardsRoot . '/tools/migrations/**/*.php') ?: [],
    static function ($migrations, string $path) {
        preg_match('/V(\d+)/', $path, $match);
        $migrations[(int) $match[1]] = pathinfo($path, PATHINFO_FILENAME);

        return $migrations;
    },
    [],
);

// Sort migrations to run them in order
ksort($migrations);

$container = new Container();

$database = $container->get(Database::class);
$dbVersion = getDBVersion($database);

foreach ($migrations as $version => $migration) {
    if ($version <= $dbVersion) {
        continue;
    }

    echo "notice: migrating from v{$dbVersion} to v{$version}" . PHP_EOL;

    $migrationClass = $container->get("tools\\migrations\\V{$version}\\{$migration}");

    try {
        $migrationClass->execute($database);
    } catch (PDOException $e) {
        echo error("Error updating to V{$version}: {$e->getMessage()}") . PHP_EOL;

        exit;
    }

    // Update DB version
    $database->update('stats', ['dbVersion' => $version]);
}

$debugLog = $container->get(DebugLog::class);
echo implode(PHP_EOL, $debugLog->getLog());

echo success('You are currently up to date! DB Version: ' . getDBVersion($database)) . PHP_EOL;
