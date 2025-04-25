<?php

declare(strict_types=1);

namespace Jax;

use MySQLi;
use mysqli_result;

use function addslashes;
use function array_keys;
use function array_map;
use function array_pop;
use function array_values;
use function error_log;
use function explode;
use function function_exists;
use function gmdate;
use function implode;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function ksort;
use function mb_check_encoding;
use function mb_convert_encoding;
use function mb_strlen;
use function mb_substr;
use function mysqli_fetch_array;
use function mysqli_fetch_assoc;
use function str_repeat;
use function str_replace;
use function time;
use function vsprintf;

use const MYSQLI_ASSOC;
use const MYSQLI_BOTH;
use const PHP_EOL;

// TODO: Migrate jaxboards to be database-independent and not tied to MySQL
final class Database
{
    public $lastQuery;

    public $debugMode = false;

    private $queryList = [];

    private $connection = false;

    private $prefix = '';

    private $usersOnlineCache = '';

    private $ratingNiblets = [];

    public function __construct(private readonly Config $config) {}

    public function connect(
        $host,
        $user,
        $password,
        $database = '',
        $prefix = '',
    ): bool {
        $this->connection = new MySQLi($host, $user, $password, $database);

        // All datetimes are GMT for jaxboards
        $this->connection->query("SET time_zone = '+0:00'");

        $this->prefix = $prefix;

        return !$this->connection->connect_errno;
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

    public function error()
    {
        if ($this->connection) {
            return $this->connection->error;
        }

        return '';
    }

    public function affectedRows()
    {
        if ($this->connection) {
            return $this->connection->affected_rows;
        }

        return -1;
    }

    public function safeselect(
        array|string $fields,
        $table,
        $where = '',
        ...$vars,
    ) {
        // set new variable to not impact debug_backtrace value for inspecting
        // input
        $fieldsString = is_array($fields) ? implode(',', $fields) : $fields;

        // Where.
        $query = 'SELECT ' . $fieldsString . ' FROM '
            . $this->ftable($table) . ($where ? ' ' . $where : '');

        return $this->safequery($query, ...$vars);
    }

    public function insertId()
    {
        if ($this->connection) {
            return $this->connection->insert_id;
        }

        return 0;
    }

    public function safeinsert($table, $data)
    {
        if (!empty($data) && array_keys($data) !== []) {
            return $this->safequery(
                'INSERT INTO ' . $this->ftable($table)
                . ' (`' . implode('`, `', array_keys($data)) . '`) VALUES ?;',
                array_values($data),
            );
        }

        return null;
    }

    public function buildInsertQuery(
        string $tableName,
        array $tableData,
    ): string {
        $columnNames = [];
        $rows = [[]];

        if (!isset($tableData[0]) || !is_array($tableData[0])) {
            $tableData = [$tableData];
        }

        foreach ($tableData as $rowIndex => $row) {
            ksort($row);
            foreach ($row as $columnName => $value) {
                if (
                    is_string($value)
                    && mb_check_encoding($value) !== 'UTF-8'
                ) {
                    $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                }

                if ($rowIndex === 0) {
                    $columnNames[] = $this->ekey($columnName);
                }

                $rows[$rowIndex][] = $this->evalue($value);
            }
        }

        foreach ($rows as $rowIndex => $rowData) {
            $rows[$rowIndex] = implode(',', $rowData);
        }

        return "INSERT INTO {$tableName}"
            . ' (' . implode(',', $columnNames) . ')'
            . ' VALUES (' . implode('),(', $rows) . ');';
    }

    public function safeupdate(
        $table,
        $kvarray,
        $whereformat = '',
        ...$whereparams,
    ) {
        if (empty($kvarray)) {
            // Nothing to update.
            return null;
        }

        $keysPrepared = $this->safeBuildUpdate($kvarray);
        $values = array_values($kvarray);
        $query = 'UPDATE ' . $this->ftable($table) . ' SET ' . $keysPrepared . ' ' . $whereformat;

        return $this->safequery($query, ...$values, ...$whereparams);
    }

    public function safeBuildUpdate($kvarray): string
    {
        if (empty($kvarray)) {
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
            array_keys($kvarray),
        ));
    }

    public function buildUpdate(array $keyValuePairs): string
    {
        $updateString = '';
        foreach ($keyValuePairs as $key => $value) {
            $updateString .= $this->eKey($key) . '=' . $this->evalue($value) . ',';
        }

        return mb_substr($updateString, 0, -1);
    }

    public function safedelete($table, $whereformat, ...$vars): mixed
    {
        $query = 'DELETE FROM ' . $this->ftable($table)
            . ($whereformat ? ' ' . $whereformat : '');

        // Put the format string back.
        return $this->safequery($query, ...$vars);
    }

    public function row($result = null): null|array|false
    {
        $result = $result ?: $this->lastQuery;

        return $result ? mysqli_fetch_array($result) : false;
    }

    // Only new-style mysqli.
    public function arows($result = null): array|false
    {
        $result = $result ?: $this->lastQuery;

        return $result ? $this->fetchAll($result, MYSQLI_ASSOC) : false;
    }

    // Only new-style mysqli.
    public function rows($result = null): array|false
    {
        $result = $result ?: $this->lastQuery;

        return $result ? $this->fetchAll($result, MYSQLI_BOTH) : false;
    }

    public function arow($result = null): null|array|false
    {
        $result = $result ?: $this->lastQuery;

        return $result ? mysqli_fetch_assoc($result) : false;
    }

    public function numRows($result = null)
    {
        $result = $result ?: $this->lastQuery;

        return $result?->num_rows ?? 0;
    }

    public function disposeresult($result): void
    {
        $result->free();
    }

    // Warning: nested arrays are *not* supported.
    public function safequery_array_types($items): string
    {
        $ret = '';
        foreach ($items as $item) {
            $ret .= $this->safequery_typeforvalue($item);
        }

        return $ret;
    }

    public function safequery_typeforvalue($value): string
    {
        if (is_array($value)) {
            return 'a';
        }

        if (is_int($value)) {
            return 'i';
        }

        return 's';
    }

    // Blah ?1 blah ?2 blah ?3 blah
    public function safequery_sub_array(
        $queryString,
        $placeholderNumber,
        $arrlen,
    ): string {
        $arr = explode('?', (string) $queryString, $placeholderNumber + 2);
        $last = array_pop($arr);
        $replacement = '';

        if ($arrlen > 0) {
            $replacement = '(' . str_repeat('?, ', $arrlen - 1) . ' ?)';
        }

        return implode('?', $arr) . $replacement . $last;
    }

    public function safequery($queryString, ...$args)
    {
        // set new variable to not impact debug_backtrace value for inspecting
        // input
        $compiledQueryString = $queryString;

        $typeString = '';
        $outArgs = [];

        $added_placeholders = 0;
        foreach ($args as $index => $value) {
            $type = $this->safequery_typeforvalue($value);

            if ($type === 'a') {
                $type = $this->safequery_array_types($value);

                $compiledQueryString = $this->safequery_sub_array(
                    $compiledQueryString,
                    $index + $added_placeholders,
                    mb_strlen($type),
                );

                $added_placeholders += mb_strlen($type) - 1;

                foreach ($value as $singleValue) {
                    $outArgs[] = $singleValue ?? '';
                }
            } else {
                $outArgs[] = $value;
            }

            $typeString .= $type;
        }

        $stmt = $this->connection->prepare($compiledQueryString);
        if ($this->debugMode) {
            $this->queryList[] = $compiledQueryString;
        }

        if (!$stmt) {
            $error = $this->connection->error;
            if ($error) {
                error_log(
                    "ERROR WITH QUERY: {$compiledQueryString}" . PHP_EOL . "{$error}",
                );
            }

            return null;
        }

        if ($args !== []) {
            $stmt->bind_param(...$this->refValues([$typeString, ...$outArgs]));
        }

        if (!$stmt->execute()) {
            $error = $this->connection->error;
            if ($error) {
                error_log(
                    "ERROR WITH QUERY: {$queryString}" . PHP_EOL . "{$error}",
                );
            }

            return null;
        }

        $retval = $stmt->get_result();

        $error = $this->connection->error;
        if ($error) {
            error_log(
                "ERROR WITH QUERY: {$queryString}" . PHP_EOL . "{$error}",
            );
        }

        return $retval;
    }

    /**
     * @param mixed $arr
     *
     * @return array<mixed>
     */
    public function refValues($arr): array
    {
        $refs = [];

        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }

        return $refs;
    }

    public function ekey($key): string
    {
        return '`' . $this->escape($key) . '`';
    }

    // Like evalue, but does not quote strings.  For use with safequery().
    public function basicvalue($value)
    {
        if (is_array($value)) {
            return $value[0];
        }

        if ($value === null) {
            return 'NULL';
        }

        return $value;
    }

    public function evalue($value)
    {
        if (is_array($value)) {
            return $value[0];
        }

        if ($value === null) {
            return 'NULL';
        }

        return is_int($value)
            ? $value
            : "'" . $this->escape($value) . "'";
    }

    public function escape($a)
    {
        if ($this->connection) {
            return $this->connection->real_escape_string($a);
        }

        return addslashes((string) $a);
    }

    public function safespecial($format, $tablenames, ...$va_array): mixed
    {
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
        return $this->safequery($newformat, ...$va_array);
    }

    public function getUsersOnline(bool $canViewHiddenMembers = false)
    {
        $idletimeout = time() - ($this->config->getSetting('timetoidle') ?? 300);
        $return = [];
        if (!$this->usersOnlineCache) {
            $result = $this->safespecial(
                <<<'EOT'
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
                    ORDER BY s.`last_action` DESC
                    EOT
                ,
                ['session', 'members'],
                gmdate('Y-m-d H:i:s', time() - $this->config->getSetting('timetologout')),
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
                if (!$user['uid']) {
                    continue;
                }

                if (isset($return[$user['uid']]) && $return[$user['uid']]) {
                    continue;
                }

                $return[$user['uid']] = $user;
            }

            $this->usersOnlineCache = $return;
        }

        return $this->usersOnlineCache;
    }

    public function fixForumLastPost($fid): void
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
            $fid,
        );
        $topic = $this->arow($result);
        $this->disposeresult($result);
        $this->safeupdate(
            'forums',
            [
                'lp_date' => isset($topic['lp_date'])
                && is_numeric($topic['lp_date'])
                && $topic['lp_date'] ? gmdate('Y-m-d H:i:s', $topic['lp_date'])
                : '0000-00-00 00:00:00',
                'lp_tid' => isset($topic['id'])
                && is_numeric($topic['id'])
                && $topic['id'] ? (int) $topic['id'] : null,
                'lp_topic' => $topic['title'] ?? '',
                'lp_uid' => isset($topic['lp_uid'])
                && is_numeric($topic['lp_uid'])
                && $topic['lp_uid'] ? (int) $topic['lp_uid'] : null,
            ],
            'WHERE id=?',
            $fid,
        );
    }

    public function fixAllForumLastPosts(): void
    {
        $query = $this->safeselect(['id'], 'forums');
        while ($forum = $this->arow($query)) {
            $this->fixForumLastPost($forum['id']);
        }
    }

    public function getRatingNiblets()
    {
        if (!empty($this->ratingNiblets)) {
            return $this->ratingNiblets;
        }

        $result = $this->safeselect(
            ['id', 'img', 'title'],
            'ratingniblets',
        );
        $niblets = [];
        while ($niblet = $this->arow($result)) {
            $niblets[$niblet['id']] = ['img' => $niblet['img'], 'title' => $niblet['title']];
        }

        return $this->ratingNiblets = $niblets;
    }

    public function debug(): string
    {
        return '<div><p>' . implode(
            '</p><p>',
            $this->queryList,
        ) . '</p></div>';
    }

    /**
     * A function to deal with the `mysqli_fetch_all` function only exiting
     * for the `mysqlnd` driver. Fetches all rows from a MySQLi query result.
     *
     * @param mysqli_result $result     the result you wish to fetch all rows from
     * @param int           $resultType The result type for each row. Should be either
     *                                  `MYSQLI_ASSOC`, `MYSQLI_NUM`, or `MYSQLI_BOTH`
     *
     * @return array an array of MySQLi result rows
     */
    private function fetchAll(
        mysqli_result $result,
        int $resultType = MYSQLI_ASSOC,
    ): array {
        if (function_exists('mysqli_fetch_all')) {
            return $result->fetch_all($resultType);
        }

        $result = [];
        while ($row = $result->fetch_array($resultType)) {
            $result[] = $row;
        }

        return $result;
    }
}
