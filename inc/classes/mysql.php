<?php

final class MySQL
{
    /**
     * @var bool
     */
    public $nolog;

    public $lastQuery;

    public $queryList = [];

    public $connected = false;

    public $mysqli_connection = false;

    public $lastfailedstatement = false;

    public $engine = 'MySQL';

    public $prefix = '';

    public $usersOnlineCache = '';

    public $ratingNiblets = [];

    public $db = '';

    public function connect(
        $host,
        $user,
        $password,
        $database = '',
        $prefix = '',
    ): true {
        $this->mysqli_connection = new mysqli($host, $user, $password, $database);
        $this->prefix = $prefix;
        $this->db = $database;

        return (bool) $this->mysqli_connection;
    }

    public function nolog(): void
    {
        $this->nolog = true;
    }

    public function prefix($a): void
    {
        $this->prefix = $a;
    }

    public function ftable($a): string
    {
        return '`' . $this->prefix . $a . '`';
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

    public function select_db($a)
    {
        if ($this->mysqli_connection->select_db($a)) {
            $this->db = $a;
        }

        return $this->db;
    }

    public function safeselect($selectors_input, $table, $where = '')
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

        // phpcs:disable PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
        $va_array = func_get_args();
        // phpcs:enable
        array_shift($va_array);
        // Selectors.
        array_shift($va_array);
        // Table.
        array_shift($va_array);
        // Where.
        $query = 'SELECT ' . $selectors . ' FROM '
            . $this->ftable($table) . ($where ? ' ' . $where : '');
        array_unshift($va_array, $query);

        return $this->safequery(...$va_array);
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

    public function buildInsert($a): array
    {
        $r = [[], [[]]];
        if (!isset($a[0]) || !is_array($a[0])) {
            $a = [$a];
        }

        foreach ($a as $k => $v) {
            ksort($v);
            foreach ($v as $k2 => $v2) {
                if (mb_check_encoding($v2) !== 'UTF-8') {
                    $v2 = mb_convert_encoding((string) $v2, 'UTF-8', 'ISO-8859-1');
                }

                if ($k === 0) {
                    $r[0][] = $this->ekey($k2);
                }

                $r[1][$k][] = $this->evalue($v2);
            }
        }

        $r[0] = implode(',', $r[0]);
        foreach ($r[1] as $k => $v) {
            $r[1][$k] = implode(',', $v);
        }

        $r[1] = '(' . implode('),(', $r[1]) . ')';

        return $r;
    }

    public function safeupdate($table, $kvarray, $whereformat = '')
    {
        if (empty($kvarray)) {
            // Nothing to update.
            return null;
        }

        // phpcs:disable PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
        $whereparams = func_get_args();
        // phpcs:enable
        array_shift($whereparams);
        // Table.
        array_shift($whereparams);
        // Key-value array.
        array_shift($whereparams);
        // Whereformat
        // $whereparams now contains the parameters for the "WHERE" clause.
        $va_array = array_merge(array_values($kvarray), $whereparams);

        $keynames = $this->safeBuildUpdate($kvarray);
        if (!empty($whereformat)) {
            $whereformat = ' ' . $whereformat;
        }

        $query = 'UPDATE ' . $this->ftable($table) . ' SET ' . $keynames . $whereformat;

        array_unshift($va_array, $query);

        return $this->safequery(...$va_array);
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

        return '`' . implode('` = ?, `', array_keys($kvarray)) . '` = ?';
    }

    public function buildUpdate($a): string
    {
        $r = '';
        foreach ($a as $k => $v) {
            $r .= $this->eKey($k) . '=' . $this->evalue($v) . ',';
        }

        return mb_substr($r, 0, -1);
    }

    public function safedelete($table, $whereformat): mixed
    {
        $query = 'DELETE FROM ' . $this->ftable($table)
            . ($whereformat ? ' ' . $whereformat : '');

        // phpcs:disable PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
        $va_array = func_get_args();
        // phpcs:enable

        array_shift($va_array);
        // Table.
        array_shift($va_array);
        // Whereformat.
        array_unshift($va_array, $query);

        // Put the format string back.
        return $this->safequery(...$va_array);
    }

    public function row($a = null): null|array|false
    {
        global $PAGE;
        $a = $a ?: $this->lastQuery;

        return $a ? mysqli_fetch_array($a) : false;
    }

    // Only new-style mysqli.
    public function arows($a = null): array|false
    {
        $a = $a ?: $this->lastQuery;
        if ($a) {
            return $this->fetchAll($a, MYSQLI_ASSOC);
        }

        return false;
    }

    // Only new-style mysqli.
    public function rows($a = null): array|false
    {
        $a = $a ?: $this->lastQuery;
        if ($a) {
            return $this->fetchAll($a, MYSQLI_BOTH);
            // Disturbingly, not MYSQLI_NUM.
        }

        return false;
    }

    public function arow($a = null): null|array|false
    {
        global $PAGE;
        $a = $a ?: $this->lastQuery;
        if ($a) {
            return @mysqli_fetch_assoc($a);
        }

        return false;
    }

    public function num_rows($a = null)
    {
        $a = $a ?: $this->lastQuery;
        if ($a) {
            return $a->num_rows;
        }

        return 0;
    }

    public function disposeresult($result): void
    {
        if (!$result) {
            syslog(
                LOG_ERR,
                'NULL RESULT in disposeresult' . PHP_EOL . print_r(
                    // phpcs:disable PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
                    debug_backtrace(),
                    // phpcs:enable
                    true,
                ),
            );

            return;
        }

        $this->fetchAll($result);
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
        $query_string,
        $placeholder_number,
        $arrlen,
    ): string {
        $arr = explode('?', (string) $query_string, $placeholder_number + 1);
        $last = array_pop($arr);

        if ($arrlen > 0) {
            $replacement = '(' . str_repeat('?, ', $arrlen - 1) . ' ?)';
        }

        return implode('?', $arr) . $replacement . $last;
    }

    public function safequery($query_string_input)
    {
        // set new variable to not impact debug_backtrace value for inspecting
        // input
        $query_string = $query_string_input;

        $my_argc = func_num_args();
        $connection = $this->mysqli_connection;

        $typestring = '';
        $out_args = [];

        $added_placeholders = 0;
        if ($my_argc > 1) {
            for ($i = 1; $i < $my_argc; ++$i) {
                // phpcs:disable PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
                $value = func_get_arg($i);
                // phpcs:enable

                $type = $this->safequery_typeforvalue($value);

                if ($type === 'a') {
                    $type = $this->safequery_array_types($value);

                    $query_string = $this->safequery_sub_array(
                        $query_string,
                        $i + $added_placeholders,
                        mb_strlen($type),
                    );

                    $added_placeholders += mb_strlen($type) - 1;

                    foreach ($value as $singlevalue) {
                        if ($singlevalue === null) {
                            $singlevalue = '';
                        }

                        $out_args[] = $singlevalue;
                    }
                } else {
                    $out_args[] = $value;
                }

                $typestring .= $type;
            }
        }

        array_unshift($out_args, $typestring);

        $stmt = $connection->prepare($query_string);
        if (!$stmt) {
            $error = $this->mysqli_connection->error;
            if ($error) {
                error_log(
                    "ERROR WITH QUERY: {$query_string}" . PHP_EOL . "{$error}",
                );
            }

            syslog(
                LOG_ERR,
                "SAFEQUERY PREPARE FAILED FOR {$query_string}, "
                . print_r($out_args, true) . PHP_EOL,
            );

            return null;
        }

        $refvalues = $this->refValues($out_args);

        if ($my_argc > 1) {
            $refclass = new ReflectionClass('mysqli_stmt');
            $method = $refclass->getMethod('bind_param');
            if (!$method->invokeArgs($stmt, $refvalues)) {
                syslog(LOG_ERR, 'BIND PARAMETERS FAILED' . PHP_EOL);
                syslog(LOG_ERR, "QUERYSTRING: {$query_string}" . PHP_EOL);
                syslog(LOG_ERR, 'ELEMENTCOUNT: ' . mb_strlen($typestring));
                syslog(LOG_ERR, 'BINDVARCOUNT: ' . count($refvalues[1]));
                syslog(LOG_ERR, 'QUERYARGS: ' . print_r($out_args, true) . PHP_EOL);
                syslog(LOG_ERR, 'REFVALUES: ' . print_r($refvalues, true) . PHP_EOL);
                // phpcs:disable PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
                syslog(LOG_ERR, print_r(debug_backtrace(), true));
                // phpcs:enable
            }
        }

        if (!$stmt->execute()) {
            $this->lastfailedstatement = $stmt;
            $error = $this->mysqli_connection->error;
            if ($error) {
                error_log(
                    "ERROR WITH QUERY: {$query_string}" . PHP_EOL . "{$error}",
                );
            }

            return null;
        }

        if (!$stmt) {
            syslog(LOG_ERR, "Statement is NULL for {$query_string}" . PHP_EOL);
        }

        $retval = $stmt->get_result();

        if (
            !$retval
            && !preg_match('/^\s*(UPDATE|DELETE|INSERT)\s/i', (string) $query_string)
        ) {
            // This is normal for a non-SELECT query.
            syslog(LOG_ERR, "Result is NULL for {$query_string}" . PHP_EOL);
        }

        $error = $this->mysqli_connection->error;
        if ($error) {
            error_log(
                "ERROR WITH QUERY: {$query_string}" . PHP_EOL . "{$error}",
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

    public function getUsersOnline()
    {
        global $CFG,$USER,$SESS;
        $idletimeout = time() - $CFG['timetoidle'];
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
                date('Y-m-d H:i:s', time() - $CFG['timetologout']),
            );
            $today = date('n j');
            while ($f = $this->arow($result)) {
                if ($f['hide']) {
                    if ($USER && $USER['group_id'] !== 2) {
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

            /*
                Since we update the session data at the END of the page,
                we'll want to include the user in the usersonline.
             */

            if ($USER && isset($return[$USER['id']]) && $return[$USER['id']]) {
                $return[$USER['id']] = [
                    'birthday' => $USER['birthday'],
                    'group_id' => $USER['group_id'],
                    'last_action' => date('Y-m-d H:i:s', $SESS->last_action),
                    'last_update' => $SESS->last_update,
                    'location' => $SESS->location,
                    'location_verbose' => $SESS->location_verbose,
                    'name' => ($SESS->hide ? '* ' : '') . $USER['display_name'],
                    'status' => $SESS->last_action < $idletimeout
                        ? 'idle'
                        : 'active',
                    'uid' => $USER['id'],
                ];
            }

            $this->usersOnlineCache = $return;
        }

        return $this->usersOnlineCache;
    }

    public function fixForumLastPost($fid): void
    {
        global $PAGE;
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
                && $d['lp_date'] ? date('Y-m-d H:i:s', $d['lp_date'])
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
        $query = $this->safeselect('`id`', 'forums');
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
            '`id`,`img`,`title`',
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
        return '<div>' . implode(
            '<br />',
            $this->queryList,
        ) . '</div>';
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
