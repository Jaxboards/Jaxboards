<?php

declare(strict_types=1);

namespace Jax;

use Carbon\Carbon;
use Exception;
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

    private PDO $pdo;

    private string $prefix = '';

    public function __construct(
        private readonly ServiceConfig $serviceConfig,
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
                );
            }
        } catch (Exception $e) {
            echo "Failed to connect to database. The following error was collected: <pre>{$e}</pre>";

            exit(1);
        }
    }

    public function connect(
        string $host,
        string $user,
        string $password,
        string $database = '',
        string $prefix = '',
    ): void {
        $this->pdo = new PDO("mysql:host={$host};dbname={$database};charset=utf8mb4", $user, $password, []);

        // All datetimes are GMT for jaxboards
        $this->pdo->query("SET time_zone = '+0:00'");

        $this->prefix = $prefix;
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
        return '`' . $this->prefix . $tableName . '`';
    }

    public function affectedRows(?PDOStatement $pdoStatement): int
    {
        return $pdoStatement?->rowCount() ?? 0;
    }

    /**
     * @param array<string>|string $fields list of fields to select, or SQL string
     * @param mixed                $vars
     */
    public function safeselect(
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

        return $this->safequery($query, ...$vars);
    }

    public function insertId(): ?string
    {
        return $this->pdo->lastInsertId() ?: null;
    }

    /**
     * @param array<string,null|float|int|string> $data
     */
    public function safeinsert(
        string $table,
        array $data,
    ): ?PDOStatement {
        if ($data === []) {
            return null;
        }

        $keys = implode(',', array_map(static fn($key): string => "`{$key}`", array_keys($data)));

        return $this->safequery(
            <<<SQL
                INSERT INTO {$this->ftable($table)} ({$keys}) VALUES ?;
                SQL,
            array_values($data),
        );
    }

    /**
     * This function was designed to create aggregate INSERT queries of many rows
     * but is currently only used to insert one row at a time.
     *
     * @param array<array<mixed>> $tableData
     */
    public function buildInsertQuery(
        string $tableName,
        array $tableData,
    ): string {
        $columnNames = [];
        $rows = [[]];

        foreach ($tableData as $rowIndex => $row) {
            ksort($row);
            foreach ($row as $columnName => $value) {
                if (
                    is_string($value)
                    && !mb_check_encoding($value, 'UTF-8')
                ) {
                    $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                }

                if ($rowIndex === 0) {
                    $columnNames[] = "`{$columnName}`";
                }

                $rows[$rowIndex][] = $this->evalue($value);
            }
        }

        $values = implode(',', array_map(
            static fn($strRow): string => "({$strRow})",
            array_map(static fn(array $row): string => implode(',', $row), $rows),
        ));

        return "INSERT INTO {$tableName}"
            . ' (' . implode(',', $columnNames) . ')'
            . " VALUES {$values};";
    }

    /**
     * @param array<string,null|float|int|string> $keyValuePairs
     * @param mixed                               $whereParams
     */
    public function safeupdate(
        string $table,
        array $keyValuePairs,
        string $whereFormat = '',
        ...$whereParams,
    ): ?PDOStatement {
        if ($keyValuePairs === []) {
            // Nothing to update.
            return null;
        }

        $keysPrepared = $this->safeBuildUpdate($keyValuePairs);
        $values = array_values($keyValuePairs);
        $query = 'UPDATE ' . $this->ftable($table) . ' SET ' . $keysPrepared . ' ' . $whereFormat;

        return $this->safequery($query, ...$values, ...$whereParams);
    }

    /**
     * @param array<string,null|float|int|string> $keyValuePairs
     */
    public function safeBuildUpdate(array $keyValuePairs): string
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

    /**
     * @param mixed $vars
     */
    public function safedelete(
        string $table,
        string $whereformat,
        ...$vars,
    ): ?PDOStatement {
        $query = 'DELETE FROM ' . $this->ftable($table)
            . ($whereformat !== '' && $whereformat !== '0' ? ' ' . $whereformat : '');

        // Put the format string back.
        return $this->safequery($query, ...$vars);
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
    public function safequery(
        string $queryString,
        ...$args,
    ): ?PDOStatement {
        // set new variable to not impact debug_backtrace value for inspecting
        // input
        $compiledQueryString = $queryString;

        $outArgs = [];

        $added_placeholders = 0;
        foreach ($args as $index => $value) {
            if (is_array($value)) {
                $valueCount = count($value);
                $compiledQueryString = $this->safeQuerySubArray(
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
                $pdoStmt->bindValue($index + 1, $value, $this->safeQueryTypeForPDOValue($value));
            }
        }

        $pdoStmt->execute();

        return $pdoStmt ?: null;
    }

    /**
     * Like evalue, but does not quote strings.  For use with safequery().
     *
     * @param mixed $value
     */
    public function basicvalue($value): int|string
    {
        if (is_array($value)) {
            return $value[0];
        }

        if ($value === null) {
            return 'NULL';
        }

        return $value;
    }

    /**
     * @param null|array<mixed>|float|int|string $value
     */
    public function evalue(null|array|float|int|string $value): int|string
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

    public function datetime(?int $timestamp = null): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @param array<int,string> $tablenames
     * @param mixed             $args
     */
    public function safespecial(
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
        return $this->safequery($newformat, ...$args);
    }

    /**
     * Returns a map of all users online with keys being user ID.
     *
     * @return array<int,array<int|string,null|int|string>>
     */
    public function getUsersOnline(bool $canViewHiddenMembers = false): array
    {
        static $usersOnlineCache = null;
        if ($usersOnlineCache) {
            return $usersOnlineCache;
        }

        $idletimeout = Carbon::now()->getTimestamp() - ($this->serviceConfig->getSetting('timetoidle') ?? 300);
        $usersOnlineCache = [];

        $result = $this->safespecial(
            <<<'SQL'
                SELECT
                    s.`id` as `id`,
                    s.`uid` AS `uid`,
                    s.`location` AS `location`,
                    s.`location_verbose` AS `location_verbose`,
                    s.`hide` AS `hide`,
                    s.`is_bot` AS `is_bot`,
                    m.`display_name` AS `name`,
                    m.`group_id` AS `group_id`,
                    m.`birthdate` AS `birthdate`,
                    CONCAT(MONTH(m.`birthdate`),' ',DAY(m.`birthdate`)) AS `dob`,
                    UNIX_TIMESTAMP(s.`last_action`) AS `last_action`,
                    UNIX_TIMESTAMP(s.`last_update`) AS `last_update`
                FROM %t s
                LEFT JOIN %t m ON s.`uid`=m.`id`
                WHERE s.`last_update`>=?
                ORDER BY s.`last_action` ASC
                SQL,
            ['session', 'members'],
            $this->datetime(Carbon::now()->getTimestamp() - $this->serviceConfig->getSetting('timetologout')),
        );
        $today = gmdate('n j');
        while ($user = $this->arow($result)) {
            if ($user['hide']) {
                if (!$canViewHiddenMembers) {
                    continue;
                }

                $user['name'] = '* ' . $user['name'];
            }

            $user['birthday'] = ($user['dob'] === $today ? 1 : 0);
            $user['status'] = $user['last_action'] < $idletimeout
                ? 'idle'
                : 'active';
            if ($user['is_bot']) {
                $user['name'] = $user['id'];
                $user['uid'] = $user['id'];
            }

            unset($user['id'], $user['dob']);

            $usersOnlineCache[$user['uid']] = $user;
        }

        return $usersOnlineCache;
    }

    public function fixForumLastPost(int $forumId): void
    {
        $result = $this->safeselect(
            [
                'lp_uid',
                'UNIX_TIMESTAMP(`lp_date`) AS `lp_date`',
                'id',
                'title',
            ],
            'topics',
            'WHERE `fid`=? ORDER BY `lp_date` DESC LIMIT 1',
            $forumId,
        );
        $topic = $this->arow($result);
        $this->disposeresult($result);
        $this->safeupdate(
            'forums',
            [
                'lp_date' => $this->datetime($topic['lp_date'] ?? 0),
                'lp_tid' => $topic['id'] ?? null,
                'lp_topic' => $topic['title'] ?? '',
                'lp_uid' => $topic['lp_uid'] ?? null,
            ],
            'WHERE id=?',
            $forumId,
        );
    }

    public function fixAllForumLastPosts(): void
    {
        $query = $this->safeselect(['id'], 'forums');
        array_map(fn($forum) => $this->fixForumLastPost((int) $forum['id']), $this->arows($query));
    }

    private function safeQueryTypeForPDOValue(
        null|bool|float|int|string $value,
    ): int {
        return match (true) {
            $value === null => PDO::PARAM_NULL,
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            default => PDO::PARAM_STR,
        };
    }

    // Blah ?1 blah ?2 blah ?3 blah
    private function safeQuerySubArray(
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
