<?php

declare(strict_types=1);

namespace Jax;

use Exception;
use MySQLite\MySQLite;
use PDO;
use PDOStatement;

use function array_keys;
use function array_map;
use function array_pop;
use function array_values;
use function count;
use function explode;
use function gmdate;
use function implode;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function ksort;
use function mb_check_encoding;
use function mb_convert_encoding;
use function str_repeat;
use function str_replace;
use function vsprintf;

use const PHP_EOL;

// phpcs:disable SlevomatCodingStandard.Classes.RequireAbstractOrFinal.ClassNeitherAbstractNorFinal
class Database
{
    // This is a bit silly, but these constants shows up so often in our codebase
    // that I'm defining them here to make our linters happy.
    public const WHERE_ID_EQUALS = 'WHERE `id`=?';

    public const WHERE_ID_IN = 'WHERE `id` IN ?';

    public const DATE = 'Y-m-d';

    public const DATE_TIME = 'Y-m-d H:i:s';

    public string $driver = 'mysql';

    private PDO $pdo;

    private string $prefix = '';

    public function __construct(
        ServiceConfig $serviceConfig,
        private readonly DebugLog $debugLog,
    ) {
        try {
            if ($serviceConfig->hasInstalled()) {
                $this->connect(
                    $serviceConfig->getSetting('sql_host'),
                    $serviceConfig->getSetting('sql_username'),
                    $serviceConfig->getSetting('sql_password'),
                    $serviceConfig->getSetting('sql_db'),
                    $serviceConfig->getSetting('sql_prefix'),
                    $serviceConfig->getSetting('sql_driver') ?? 'mysql',
                );
            }
        } catch (Exception $e) {
            echo "Failed to connect to database. The following error was collected: <pre>{$e}</pre>";

            exit(1);
        }

        Model::setDatabase($this);
    }

    public function connect(
        string $host = '',
        string $user = '',
        string $password = '',
        string $database = '',
        string $prefix = '',
        string $driver = '',
    ): void {
        $connectionArgs = match ($driver) {
            'postgres' => [
                "postgres:host={$host};dbname={$database}",
                $user,
                $password,
            ],
            'sqliteMemory' => ['sqlite::memory:'],
            default => [
                "mysql:host={$host};dbname={$database};charset=utf8mb4",
                $user,
                $password,
            ],
        };

        $this->pdo = new PDO(...$connectionArgs);

        // All datetimes are GMT for jaxboards
        match ($driver) {
            'postgres' => $this->pdo->query('SET TIME ZONE "UTC"'),
            'sqliteMemory' => MySQLite::install($this->pdo),
            default => $this->pdo->query("SET time_zone = '+0:00'"),
        };

        $this->setPrefix($prefix);
        $this->driver = $driver;
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function ftable(string $tableName): string
    {
        return $this->quoteIdentifier($this->prefix . $tableName);
    }

    public function affectedRows(?PDOStatement $pdoStatement): int
    {
        return $pdoStatement?->rowCount() ?? 0;
    }

    /**
     * @param array<string>|string $fields list of fields to select, or SQL string
     * @param mixed                $vars
     */
    public function select(
        array|string $fields,
        string $table,
        ?string $where = null,
        ...$vars,
    ): ?PDOStatement {
        // set new variable to not impact debug_backtrace value for inspecting
        // input
        $fieldsString = is_array($fields) ? implode(', ', $fields) : $fields;

        // Where.
        $query = "SELECT {$fieldsString} FROM "
            . $this->ftable($table) . ($where !== null ? ' ' . $where : '');

        return $this->query($query, ...$vars);
    }

    public function insertId(): ?string
    {
        return $this->pdo->lastInsertId() ?: null;
    }

    /**
     * @param array<string,null|float|int|string> $data
     */
    public function insert(string $table, array $data): ?PDOStatement
    {
        if ($data === []) {
            return null;
        }

        $keys = [];
        $values = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $keys[] = $this->quoteIdentifier($key);
            $values[] = $value;
        }

        $keys = implode(',', $keys);

        return $this->query(
            <<<SQL
                INSERT INTO {$this->ftable($table)} ({$keys}) VALUES ?;
                SQL,
            $values,
        );
    }

    /**
     * @param array<string,null|float|int|string> $keyValuePairs
     * @param mixed                               $whereParams
     */
    public function update(
        string $table,
        array $keyValuePairs,
        string $whereFormat = '',
        ...$whereParams,
    ): ?PDOStatement {
        if ($keyValuePairs === []) {
            // Nothing to update.
            return null;
        }

        $keysPrepared = $this->buildUpdate($keyValuePairs);
        $values = array_values($keyValuePairs);
        $query = 'UPDATE ' . $this->ftable($table) . ' SET ' . $keysPrepared . ' ' . $whereFormat;

        return $this->query($query, ...$values, ...$whereParams);
    }

    /**
     * @param mixed $vars
     */
    public function delete(
        string $table,
        string $whereformat,
        ...$vars,
    ): ?PDOStatement {
        $query = 'DELETE FROM ' . $this->ftable($table)
            . ($whereformat !== '' ? ' ' . $whereformat : '');

        // Put the format string back.
        return $this->query($query, ...$vars);
    }

    /**
     * Returns a single record.
     *
     * @return ?array<string,mixed>
     */
    public function arow(?PDOStatement $pdoStatement = null): ?array
    {
        return $pdoStatement?->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Returns multiple records.
     *
     * @return array<int,array<string,mixed>>
     */
    public function arows(?PDOStatement $pdoStatement = null): array
    {
        return $pdoStatement?->fetchAll(PDO::FETCH_ASSOC) ?? [];
    }

    public function disposeresult(?PDOStatement $pdoStatement): void
    {
        $pdoStatement?->closeCursor();
    }

    /**
     * @param null|array<null|bool|float|int|string>|bool|float|int|string ...$args
     */
    public function query(string $queryString, ...$args): ?PDOStatement
    {
        // set new variable to not impact debug_backtrace value for inspecting
        // input
        $compiledQueryString = $queryString;

        $outArgs = [];

        $added_placeholders = 0;
        foreach ($args as $index => $value) {
            if (is_array($value)) {
                $valueCount = count($value);
                $compiledQueryString = $this->querySubArray(
                    $compiledQueryString,
                    ((int) $index) + $added_placeholders,
                    $valueCount,
                );

                $added_placeholders += $valueCount - 1;

                foreach ($value as $singleValue) {
                    $outArgs[] = $singleValue ?? '';
                }

                continue;
            }

            $outArgs[] = $value;
        }

        $pdoStmt = $this->pdo->prepare($compiledQueryString);

        $this->debugLog->log($compiledQueryString, 'Queries');

        if ($args !== []) {
            foreach ($outArgs as $index => $value) {
                $pdoStmt->bindValue($index + 1, $value, $this->queryTypeForPDOValue($value));
            }
        }

        $pdoStmt->execute();

        return $pdoStmt ?: null;
    }

    /**
     * @param null|array<mixed>|float|int|string $value
     */
    public function evalue(array|float|int|string|null $value): int|string
    {
        if (is_array($value)) {
            return $value[0];
        }

        if ($value === null) {
            return 'NULL';
        }

        return is_int($value)
            ? $value
            : $this->escape((string) $value);
    }

    public function escape(string $string): string
    {
        return $this->pdo->quote($string);
    }

    public function quoteIdentifier(string $identifier): string
    {
        $quote = $this->driver === 'pgsql' ? '"' : '`';

        return $quote . $identifier . $quote;
    }

    public function date(?int $timestamp = null): string
    {
        return gmdate(self::DATE, $timestamp);
    }

    public function datetime(?int $timestamp = null): string
    {
        return gmdate(self::DATE_TIME, $timestamp);
    }

    /**
     * @param array<int,string> $tablenames
     * @param mixed             $args
     */
    public function special(
        string $format,
        array $tablenames,
        ...$args,
    ): mixed {
        // Table names.
        $tempformat = str_replace('%t', '%s', $format);

        $newformat = vsprintf(
            $tempformat,
            array_map(
                $this->ftable(...),
                $tablenames,
            ),
        );

        // Put the format string back.
        return $this->query($newformat, ...$args);
    }

    /**
     * @param array<string,null|float|int|string> $keyValuePairs
     */
    private function buildUpdate(array $keyValuePairs): string
    {
        if ($keyValuePairs === []) {
            return '';
        }

        /*
            E.G. if array is a => b; c => c; then result is a = ?, b = ?,
            where the first " = ?," comes from the implode.
         */

        return implode(PHP_EOL . ', ', array_map(
            static function (string $key): string {
                $value = '?';

                return "`{$key}` = {$value}";
            },
            array_keys($keyValuePairs),
        ));
    }

    private function queryTypeForPDOValue(
        bool|float|int|string|null $value,
    ): int {
        return match (true) {
            $value === null => PDO::PARAM_NULL,
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            default => PDO::PARAM_STR,
        };
    }

    // Blah ?1 blah ?2 blah ?3 blah
    private function querySubArray(
        string $queryString,
        int $placeholderNumber,
        int $arrlen,
    ): string {
        $arr = explode('?', $queryString, $placeholderNumber + 2);
        $last = array_pop($arr);
        $replacement = '';

        if ($arrlen > 0) {
            $replacement = '(' . str_repeat('?, ', $arrlen - 1) . ' ?)';
        }

        return implode('?', $arr) . $replacement . $last;
    }
}
