<?php

declare(strict_types=1);

namespace Jax;

use MySQLi;
use mysqli_result;
use ReflectionClass;

use function addslashes;
use function array_keys;
use function array_map;
use function array_pop;
use function array_shift;
use function array_unshift;
use function array_values;
use function count;
use function debug_backtrace;
use function error_log;
use function explode;
use function func_get_arg;
use function func_num_args;
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
use function preg_match;
use function print_r;
use function str_repeat;
use function str_replace;
use function syslog;
use function time;
use function vsprintf;

use const LOG_ERR;
use const MYSQLI_ASSOC;
use const MYSQLI_BOTH;
use const PHP_EOL;

// TODO: Migrate jaxboards to be database-independent and not tied to MySQL
final class Database
{
    public $lastQuery;

    public $debugMode = false;

    private $queryList = [];

    private $mysqli_connection = false;

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
        $this->mysqli_connection = new MySQLi($host, $user, $password, $database);

        // All datetimes are GMT for jaxboards
        $this->mysqli_connection->query("SET time_zone = '+0:00'");

        $this->prefix = $prefix;

        return !$this->mysqli_connection->connect_errno;
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
        if ($this->mysqli_connection) {
            return $this->mysqli_connection->error;
        }

        return '';
    }

    public function affected_rows()
    {
        if ($this->mysqli_connection) {
            return $this->mysqli_connection->affected_rows;
        }

        return -1;
    }

    public function safeselect($selectors_input, $table, $where = '', ...$vars)
    {
        // set new variable to not impact debug_backtrace value for inspecting
        // input
        $selectors = $selectors_input;
        if (is_array($selectors)) {
            $selectors = implode(',', $selectors);
        } elseif (!is_string($selectors)) {
            return null;
        }

        if (mb_strlen($selectors) < 1) {
            return null;
        }

        // Where.
        $query = 'SELECT ' . $selectors . ' FROM '
            . $this->ftable($table) . ($where ? ' ' . $where : '');

        return $this->safequery($query, ...$vars);
    }

    public function insert_id()
    {
        if ($this->mysqli_connection) {
            return $this->mysqli_connection->insert_id;
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
            . ' VALUES (' . implode('),(', $rows) . ')';
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
        if ($result) {
            return $this->fetchAll($result, MYSQLI_ASSOC);
        }

        return false;
    }

    // Only new-style mysqli.
    public function rows($result = null): array|false
    {
        $result = $result ?: $this->lastQuery;
        if ($result) {
            return $this->fetchAll($result, MYSQLI_BOTH);
            // Disturbingly, not MYSQLI_NUM.
        }

        return false;
    }

    public function arow($result = null): null|array|false
    {
        $result = $result ?: $this->lastQuery;
        if ($result) {
            return @mysqli_fetch_assoc($result);
        }

        return false;
    }

    public function num_rows($result = null)
    {
        $result = $result ?: $this->lastQuery;
        if ($result) {
            return $result->num_rows;
        }

        return 0;
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
        $type = 's';
        if (is_array($value)) {
            $type = 'a';
        }

        if (is_int($value)) {
            return 'i';
        }

        return $type;
    }

    // Blah ?1 blah ?2 blah ?3 blah
    // Note that placeholder_number is indexed from 1.
    public function safequery_sub_array(
        $queryString,
        $placeholder_number,
        $arrlen,
    ): string {
        $arr = explode('?', (string) $queryString, $placeholder_number + 1);
        $last = array_pop($arr);
        $replacement = '';

        if ($arrlen > 0) {
            $replacement = '(' . str_repeat('?, ', $arrlen - 1) . ' ?)';
        }

        return implode('?', $arr) . $replacement . $last;
    }

    public function safequery($queryString_input)
    {
        // set new variable to not impact debug_backtrace value for inspecting
        // input
        $queryString = $queryString_input;

        $my_argc = func_num_args();
        $connection = $this->mysqli_connection;

        $typestring = '';
        $outArgs = [];

        $added_placeholders = 0;
        if ($my_argc > 1) {
            for ($i = 1; $i < $my_argc; ++$i) {
                // phpcs:disable PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
                $value = func_get_arg($i);
                // phpcs:enable

                $type = $this->safequery_typeforvalue($value);

                if ($type === 'a') {
                    $type = $this->safequery_array_types($value);

                    $queryString = $this->safequery_sub_array(
                        $queryString,
                        $i + $added_placeholders,
                        mb_strlen($type),
                    );

                    $added_placeholders += mb_strlen($type) - 1;

                    foreach ($value as $singlevalue) {
                        if ($singlevalue === null) {
                            $singlevalue = '';
                        }

                        $outArgs[] = $singlevalue;
                    }
                } else {
                    $outArgs[] = $value;
                }

                $typestring .= $type;
            }
        }

        array_unshift($outArgs, $typestring);

        $stmt = $connection->prepare($queryString);
        if ($this->debugMode) {
            $this->queryList[] = $queryString;
        }

        if (!$stmt) {
            $error = $this->mysqli_connection->error;
            if ($error) {
                error_log(
                    "ERROR WITH QUERY: {$queryString}" . PHP_EOL . "{$error}",
                );
            }

            syslog(
                LOG_ERR,
                "SAFEQUERY PREPARE FAILED FOR {$queryString}, "
                . print_r($outArgs, true) . PHP_EOL,
            );

            return null;
        }

        $refvalues = $this->refValues($outArgs);

        if ($my_argc > 1) {
            $refclass = new ReflectionClass('mysqli_stmt');
            $method = $refclass->getMethod('bind_param');
            if (!$method->invokeArgs($stmt, $refvalues)) {
                syslog(LOG_ERR, 'BIND PARAMETERS FAILED' . PHP_EOL);
                syslog(LOG_ERR, "QUERYSTRING: {$queryString}" . PHP_EOL);
                syslog(LOG_ERR, 'ELEMENTCOUNT: ' . mb_strlen($typestring));
                syslog(LOG_ERR, 'BINDVARCOUNT: ' . count($refvalues[1]));
                syslog(LOG_ERR, 'QUERYARGS: ' . print_r($outArgs, true) . PHP_EOL);
                syslog(LOG_ERR, 'REFVALUES: ' . print_r($refvalues, true) . PHP_EOL);
                // phpcs:disable PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
                syslog(LOG_ERR, print_r(debug_backtrace(), true));
                // phpcs:enable
            }
        }

        if (!$stmt->execute()) {
            $error = $this->mysqli_connection->error;
            if ($error) {
                error_log(
                    "ERROR WITH QUERY: {$queryString}" . PHP_EOL . "{$error}",
                );
            }

            return null;
        }

        if (!$stmt) {
            syslog(LOG_ERR, "Statement is NULL for {$queryString}" . PHP_EOL);
        }

        $retval = $stmt->get_result();

        if (
            !$retval
            && !preg_match('/^\s*(UPDATE|DELETE|INSERT)\s/i', (string) $queryString)
        ) {
            // This is normal for a non-SELECT query.
            syslog(LOG_ERR, "Result is NULL for {$queryString}" . PHP_EOL);
        }

        $error = $this->mysqli_connection->error;
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

    public function evalue($value, $forsprintf = 0)
    {
        if (is_array($value)) {
            $value = $value[0];
        } elseif ($value === null) {
            $value = 'NULL';
        } else {
            $value = is_int($value)
                ? $value
                : "'" . $this->escape(
                    $forsprintf ? str_replace('%', '%%', $value) : $value,
                ) . "'";
        }

        return $value;
    }

    public function escape($a)
    {
        if ($this->mysqli_connection) {
            return $this->mysqli_connection->real_escape_string($a);
        }

        return addslashes((string) $a);
    }

    public function safespecial(...$va_array): mixed
    {
        $format = array_shift($va_array);
        // Format.
        $tablenames = array_shift($va_array);
        // Table names.
        $tempformat = str_replace('%t', '%s', $format);

        if (!$tablenames) {
            syslog(
                LOG_ERR,
                'NO TABLE NAMES' . PHP_EOL . print_r(
                    debug_backtrace(),
                    true,
                ),
            );
        }

        $newformat = vsprintf(
            $tempformat,
            array_map(
                $this->ftable(...),
                $tablenames,
            ),
        );

        array_unshift($va_array, $newformat);

        // Put the format string back.
        return $this->safequery(...$va_array);
    }

    public function getUsersOnline(bool $canViewHiddenMembers = false)
    {
        $idletimeout = time() - ($this->config->getSetting('timetoidle') ?? 300);
        $return = [];
        if (!$this->usersOnlineCache) {
            $result = $this->safespecial(
                <<<'EOT'
                    SELECT a.`id` as `id`,a.`uid` AS `uid`,a.`location` AS `location`,
                        a.`location_verbose` AS `location_verbose`,a.`hide` AS `hide`,
                        a.`is_bot` AS `is_bot`,b.`display_name` AS `name`,
                        b.`group_id` AS `group_id`,b.`birthdate` AS `birthdate`,
                        CONCAT(MONTH(b.`birthdate`),' ',DAY(b.`birthdate`)) AS `dob`,
                        UNIX_TIMESTAMP(a.`last_action`) AS `last_action`,
                        UNIX_TIMESTAMP(a.`last_update`) AS `last_update`
                    FROM %t a
                    LEFT JOIN %t b
                        ON a.`uid`=b.`id`
                    WHERE a.`last_update`>=?
                    ORDER BY a.`last_action` DESC
                    EOT
                ,
                ['session', 'members'],
                gmdate('Y-m-d H:i:s', time() - $this->config->getSetting('timetologout')),
            );
            $today = gmdate('n j');
            while ($f = $this->arow($result)) {
                if ($f['hide']) {
                    if (!$canViewHiddenMembers) {
                        continue;
                    }

                    $f['name'] = '* ' . $f['name'];
                }

                $f['birthday'] = ($f['dob'] === $today ? 1 : 0);
                $f['status'] = $f['last_action'] < $idletimeout
                    ? 'idle'
                    : 'active';
                if ($f['is_bot']) {
                    $f['name'] = $f['id'];
                    $f['uid'] = $f['id'];
                }

                unset($f['id'], $f['dob']);
                if (!$f['uid']) {
                    continue;
                }

                if (isset($return[$f['uid']]) && $return[$f['uid']]) {
                    continue;
                }

                $return[$f['uid']] = $f;
            }

            $this->usersOnlineCache = $return;
        }

        return $this->usersOnlineCache;
    }

    public function fixForumLastPost($fid): void
    {
        $result = $this->safeselect(
            '`lp_uid`,UNIX_TIMESTAMP(`lp_date`) AS `lp_date`,`id`,`title`',
            'topics',
            'WHERE `fid`=? ORDER BY `lp_date` DESC LIMIT 1',
            $fid,
        );
        $d = $this->arow($result);
        $this->disposeresult($result);
        $this->safeupdate(
            'forums',
            [
                'lp_date' => isset($d['lp_date'])
                && is_numeric($d['lp_date'])
                && $d['lp_date'] ? gmdate('Y-m-d H:i:s', $d['lp_date'])
                : '0000-00-00 00:00:00',
                'lp_tid' => isset($d['id'])
                && is_numeric($d['id'])
                && $d['id'] ? (int) $d['id'] : null,
                'lp_topic' => $d['title'] ?? '',
                'lp_uid' => isset($d['lp_uid'])
                && is_numeric($d['lp_uid'])
                && $d['lp_uid'] ? (int) $d['lp_uid'] : null,
            ],
            'WHERE id=?',
            $fid,
        );
    }

    public function fixAllForumLastPosts(): void
    {
        $query = $this->safeselect(['id'], 'forums');
        while ($fid = $this->arow($query)) {
            $this->fixForumLastPost($fid['id']);
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
        $r = [];
        while ($f = $this->arow($result)) {
            $r[$f['id']] = ['img' => $f['img'], 'title' => $f['title']];
        }

        return $this->ratingNiblets = $r;
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
