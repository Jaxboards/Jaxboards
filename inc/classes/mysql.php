<?php
// fetch_all not available in some environments
function fetch_all($result,$resulttype = MYSQLI_NUM) {
  for ($res = array(); $tmp = mysqli_fetch_array($result);) $res[] = $tmp;
  return $res;
}


class MySQL
{
    public $lastQuery;
    public $queryList = array();
    public $queryRuntime = array();
    public $connected = false;
    public $mysqli_connection = false;
    public $lastfailedstatement = false;
    public $engine = 'MySQL';
    public $prefix = '';
    public $usersOnlineCache = '';
    public $ratingNiblets = array();
    public $db = '';

    public function connect($host, $user, $password, $database = '', $prefix = '')
    {
        $this->mysqli_connection = new mysqli($host, $user, $password, $database);
        $this->prefix = $prefix;
        $this->db = $database;
        if (!$this->mysqli_connection) {
            return false;
        }

        return true;
    }

    public function debug_mode()
    {
        $this->debugMode = true;
    }

    public function nolog()
    {
        $this->nolog = true;
    }

    public function prefix($a)
    {
        $this->prefix = $a;
    }

    public function ftable($a)
    {
        return '`' . $this->prefix . $a . '`';
    }

    public function error($use_mysqli = 0)
    {
        if ($this->mysqli_connection) {
            return $this->mysqli_connection->error;
        }

        return '';
    }

    public function affected_rows($use_mysqli = 0)
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

    public function safeselect($selectors, $table, $where = '')
    {
        if (is_array($selectors)) {
            $selectors = implode(',', $selectors);
        } elseif (!is_string($selectors)) {
            return;
        }
        if (mb_strlen($selectors) < 1) {
            return;
        }
        $va_array = func_get_args();
        array_shift($va_array);
        // Selectors.
        array_shift($va_array);
        // Table.
        array_shift($va_array);
        // Where.
        $query = 'SELECT ' . $selectors . ' FROM ' .
            $this->ftable($table) . ($where ? ' ' . $where : '');
        array_unshift($va_array, $query);

        return call_user_func_array(array($this, 'safequery'), $va_array);
    }

    public function insert_id($use_mysqli = 0)
    {
        if ($this->mysqli_connection) {
            return $this->mysqli_connection->insert_id;
        }

        return 0;
    }

    public function safeinsert($table, $data)
    {
        if (!empty($data) && count(array_keys($data)) > 0) {
            return $this->safequery(
                'INSERT INTO ' . $this->ftable($table) .
                ' (`' . implode('`, `', array_keys($data)) . '`) VALUES ?;',
                array_values($data)
            );
        }
    }

    public function buildInsert($a)
    {
        $r = array(array(), array(array()));
        if (!isset($a[0]) || !is_array($a[0])) {
            $a = array($a);
        }

        foreach ($a as $k => $v) {
            ksort($v);
            foreach ($v as $k2 => $v2) {
                if ('UTF-8' != mb_check_encoding($v2)) {
                    $v2 = utf8_encode($v2);
                }
                if (0 == $k) {
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
            return;
        }
        $whereparams = func_get_args();
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

        return call_user_func_array(array($this, 'safequery'), $va_array);
    }

    public function safeBuildUpdate($kvarray)
    {
        if (empty($kvarray)) {
            return '';
        }

        /*
         * e.g. if array is a => b; c => c; then result is a = ?, b = ?,
         * where the first " = ?," comes from the implode.
         */

        return '`' . implode('` = ?, `', array_keys($kvarray)) . '` = ?';
    }

    public function buildUpdate($a)
    {
        $r = '';
        foreach ($a as $k => $v) {
            $r .= $this->eKey($k) . '=' . $this->evalue($v) . ',';
        }

        return mb_substr($r, 0, -1);
    }

    public function safedelete($table, $whereformat)
    {
        $query = 'DELETE FROM ' . $this->ftable($table) .
            ($whereformat ? ' ' . $whereformat : '');

        $va_array = func_get_args();

        array_shift($va_array);
        // Table.
        array_shift($va_array);
        // Whereformat.
        array_unshift($va_array, $query);
        // Put the format string back.
        return call_user_func_array(array($this, 'safequery'), $va_array);
    }

    public function row($a = null)
    {
        global $PAGE;
        $a = $a ? $a : $this->lastQuery;
        $ret = $a ? mysqli_fetch_array($a) : false;

        return $ret;
    }

    // Only new-style mysqli.
    public function arows($a = null)
    {
        $a = $a ? $a : $this->lastQuery;
        if ($a) {
            return fetch_all($a, MYSQLI_ASSOC);
        }

        return false;
    }

    // Only new-style mysqli.
    public function rows($a = null)
    {
        $a = $a ? $a : $this->lastQuery;
        if ($a) {
            return fetch_all($a, MYSQLI_BOTH);
            // Disturbingly, not MYSQLI_NUM.
        }

        return false;
    }

    public function arow($a = null)
    {
        global $PAGE;
        $a = $a ? $a : $this->lastQuery;
        if ($a) {
            $q = @mysqli_fetch_assoc($a);
        } else {
            $q = false;
        }

        return $q;
    }

    public function num_rows($a = null)
    {
        $a = $a ? $a : $this->lastQuery;
        if ($a) {
            return $a->num_rows;
        }

        return 0;
    }

    public function disposeresult($result)
    {
        if (!$result) {
            syslog(
                LOG_ERR,
                'NULL RESULT in disposeresult' . PHP_EOL . print_r(
                    debug_backtrace(),
                    true
                )
            );

            return;
        }
        fetch_all($result);
    }

    // Warning: nested arrays are *not* supported.
    public function safequery_array_types($items)
    {
        $ret = '';
        foreach ($items as $item) {
            $ret .= $this->safequery_typeforvalue($item);
        }

        return $ret;
    }

    public function safequery_typeforvalue($value)
    {
        $type = 's';
        if (is_array($value)) {
            $type = 'a';
        }
        if (is_int($value)) {
            $type = 'i';
        }

        return $type;
    }

    // Blah ?1 blah ?2 blah ?3 blah
    // Note that placeholder_number is indexed from 1.
    public function safequery_sub_array($query_string, $placeholder_number, $arrlen)
    {
        $arr = explode('?', $query_string, $placeholder_number + 1);
        $last = array_pop($arr);

        if ($arrlen > 0) {
            $replacement = '(' . str_repeat('?, ', ($arrlen) - 1) . ' ?)';
        }

        return implode('?', $arr) . $replacement . $last;
    }

    public function safequery($query_string)
    {
        $my_argc = func_num_args();
        $connection = $this->mysqli_connection;

        $typestring = '';
        $out_args = array();

        $added_placeholders = 0;
        if ($my_argc > 1) {
            for ($i = 1; $i < $my_argc; ++$i) {
                $value = func_get_arg($i);

                $type = $this->safequery_typeforvalue($value);

                if ('a' == $type) {
                    $type = $this->safequery_array_types($value);

                    $query_string = $this->safequery_sub_array(
                        $query_string,
                        $i + $added_placeholders,
                        mb_strlen($type)
                    );

                    $added_placeholders += mb_strlen($type) - 1;

                    foreach ($value as $singlevalue) {
                        if (null === $singlevalue) {
                            $singlevalue = '';
                        }
                        array_push($out_args, $singlevalue);
                    }
                } else {
                    array_push($out_args, $value);
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
                    "ERROR WITH QUERY: ${query_string}" . PHP_EOL . "${error}"
                );
            }
            syslog(
                LOG_ERR,
                "SAFEQUERY PREPARE FAILED FOR ${query_string}, " .
                print_r($out_args, true) . PHP_EOL
            );

            return;
        }

        $refvalues = $this->refValues($out_args);

        if ($my_argc > 1) {
            $refclass = new ReflectionClass('mysqli_stmt');
            $method = $refclass->getMethod('bind_param');
            if (!$method->invokeArgs($stmt, $refvalues)) {
                syslog(LOG_ERR, 'BIND PARAMETERS FAILED' . PHP_EOL);
                syslog(LOG_ERR, "QUERYSTRING: ${query_string}" . PHP_EOL);
                syslog(LOG_ERR, 'ELEMENTCOUNT: ' . mb_strlen($typestring));
                syslog(LOG_ERR, 'BINDVARCOUNT: ' . (count($refvalues[1])));
                syslog(LOG_ERR, 'QUERYARGS: ' . print_r($out_args, true) . PHP_EOL);
                syslog(LOG_ERR, 'REFVALUES: ' . print_r($refvalues, true) . PHP_EOL);
                syslog(LOG_ERR, print_r(debug_backtrace(), true));
            }
        }

        if (!$stmt->execute()) {
            $this->lastfailedstatement = $stmt;
            $error = $this->mysqli_connection->error;
            if ($error) {
                error_log(
                    "ERROR WITH QUERY: ${query_string}" . PHP_EOL . "${error}"
                );
            }

            return;
        }
        if (!$stmt) {
            syslog(LOG_ERR, "Statement is NULL for ${query_string}" . PHP_EOL);
        }
        $retval = $stmt->get_result();

        if (!$retval) {
            if (!preg_match('/^\\s*(UPDATE|DELETE|INSERT)\\s/i', $query_string)) {
                // This is normal for a non-SELECT query.
                syslog(LOG_ERR, "Result is NULL for ${query_string}" . PHP_EOL);
            }
        }

        $error = $this->mysqli_connection->error;
        if ($error) {
            error_log(
                "ERROR WITH QUERY: ${query_string}" . PHP_EOL . "${error}"
            );
        }

        return $retval;
    }

    public function refValues($arr)
    {
        $refs = array();

        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }

        return $refs;
    }

    public function ekey($key)
    {
        return '`' . $this->escape($key) . '`';
    }

    // Like evalue, but does not quote strings.  For use with safequery().
    public function basicvalue($value, $forsprintf = 0)
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
            $value = is_int($value) ? $value :
                '\'' . $this->escape(
                    ($forsprintf ? str_replace('%', '%%', $value) : $value)
                ) . '\'';
        }

        return $value;
    }

    public function escape($a)
    {
        if ($this->mysqli_connection) {
            return $this->mysqli_connection->real_escape_string($a);
        }

        return addslashes($a);
    }

    public function safespecial()
    {
        $va_array = func_get_args();

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
                    true
                )
            );
        }

        $newformat = vsprintf(
            $tempformat,
            array_map(
                array(
                    $this,
                    'ftable',
                ),
                $tablenames
            )
        );

        array_unshift($va_array, $newformat);
// Put the format string back.
        return call_user_func_array(array($this, 'safequery'), $va_array);
    }

    public function getUsersOnline()
    {
        global $CFG,$USER,$SESS;
        $idletimeout = time() - $CFG['timetoidle'];
        $r = array('guestcount' => 0);
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
                array('session', 'members'),
                date('Y-m-d H:i:s', (time() - $CFG['timetologout']))
            );
            $today = date('n j');
            while ($f = $this->arow($result)) {
                if ($f['hide']) {
                    if (2 != $USER['group_id']) {
                        continue;
                    }
                    $f['name'] = '* ' . $f['name'];
                }
                $f['birthday'] = ($f['dob'] == $today ? 1 : 0);
                $f['status'] = $f['last_action'] < $idletimeout ?
                    'idle' : 'active';
                if ($f['is_bot']) {
                    $f['name'] = $f['id'];
                    $f['uid'] = $f['id'];
                }
                unset($f['id'], $f['dob']);
                if ($f['uid']) {
                    if (!isset($r[$f['uid']]) || !$r[$f['uid']]) {
                        $r[$f['uid']] = $f;
                    }
                } else {
                    ++$r['guestcount'];
                }
            }

            /*
             * since we update the session data at the END of the page,
             * we'll want to include the user in the usersonline
             */

            if ($USER && isset($r[$USER['id']]) && $r[$USER['id']]) {
                $r[$USER['id']] = array(
                    'uid' => $USER['id'],
                    'group_id' => $USER['group_id'],
                    'last_action' => date('Y-m-d H:i:s', $SESS->last_action),
                    'last_update' => date('Y-m-d H:i:s', $SESS->last_update),
                    'name' => ($SESS->hide ? '* ' : '') . $USER['display_name'],
                    'status' => $SESS->last_action < $idletimeout ?
                    'idle' : 'active',
                    'birthday' => $USER['birthday'],
                    'location' => $SESS->location,
                    'location_verbose' => $SESS->location_verbose,
                );
            }
            $this->usersOnlineCache = $r;
        }

        return $this->usersOnlineCache;
    }

    public function fixForumLastPost($fid)
    {
        global $PAGE;
        $result = $this->safeselect(
            '`lp_uid`,UNIX_TIMESTAMP(`lp_date`) AS `lp_date`,`id`,`title`',
            'topics',
            'WHERE `fid`=? ORDER BY `lp_date` DESC LIMIT 1',
            $fid
        );
        $d = $this->arow($result);
        $this->disposeresult($result);
        $this->safeupdate(
            'forums',
            array(
                'lp_uid' => (isset($d['lp_uid'])
                && is_numeric($d['lp_uid'])
                && $d['lp_uid']) ? (int) $d['lp_uid'] : null,
                'lp_date' => (isset($d['lp_date'])
                && is_numeric($d['lp_date'])
                && $d['lp_date']) ? date('Y-m-d H:i:s', $d['lp_date']) :
                '0000-00-00 00:00:00',
                'lp_tid' => (isset($d['id'])
                && is_numeric($d['id'])
                && $d['id']) ? (int) $d['id'] : null,
                'lp_topic' => isset($d['title']) ? $d['title'] : '',
            ),
            'WHERE id=?',
            $fid
        );
    }

    public function fixAllForumLastPosts()
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
            'ratingniblets'
        );
        $r = array();
        while ($f = $this->arow($result)) {
            $r[$f['id']] = array('img' => $f['img'], 'title' => $f['title']);
        }

        return $this->ratingNiblets = $r;
    }

    public function debug()
    {
        return '<div>' . implode(
            '<br />',
            $this->queryList
        ) . '</div>';
        $this->queryList = array();
    }
}
