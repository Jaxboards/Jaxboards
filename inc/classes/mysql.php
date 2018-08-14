<?php

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

    public function connect($host, $user, $password, $database = '', $prefix = '')
    {
        $this->mysqli_connection = new mysqli($host, $user, $password, $database);
        $this->prefix = $prefix;
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
        return '`'.$this->prefix.$a.'`';
    }

    public function error($use_mysqli = 0)
    {
        if (function_exists('mysql_error')) {
            return $use_mysqli ? $this->mysqli_connection->error : mysql_error();
        }
        if ($this->mysqli_connection) {
            return $this->mysqli_connection->error;
        }

        return '';
    }

    public function affected_rows($use_mysqli = 0)
    {
        if ($use_mysqli) {
            return $this->mysqli_connection->affected_rows;
        }

        return mysql_affected_rows();
    }

    public function select_db($a)
    {
        if (mysql_select_db($a) && $this->mysqli_connection->select_db($a)) {
            $this->db = $a;
        }

        return $this->db;
    }

    public function safeselect($selectors, $table, $where = '' /*, ... */)
    {
        $va_array = func_get_args();
        array_shift($va_array); // selectors
    array_shift($va_array); // table
    array_shift($va_array); // where

    $query = 'SELECT '.$selectors.' FROM '.$this->ftable($table).($where ? ' '.$where : '');
        array_unshift($va_array, $query);

        // syslog(LOG_ERR, "ARRAY: ".print_r($va_array, true));

        return call_user_func_array(array($this, 'safequery'), $va_array);
    }

    // function select($a,$b,$c='',$over=1){
    // return $this->query('SELECT '.$a.' FROM '.$this->ftable($b).($c?' '.$c:''),$over);
    // }

    public function insert_id($use_mysqli = 0)
    {
        if ($use_mysqli) {
            return $this->mysqli_connection->insert_id;
        }

        return mysql_insert_id();
    }

    public function safeinsert($a, $b)
    {
        return $this->safequery('INSERT INTO '.$this->ftable($a).' (`'.implode('`, `', array_keys($b)).'`) VALUES ?;',
    array_values($b));
    }

    // function insert($a,$b){
    // $b=$this->buildInsert($b);
    // return $this->query('INSERT INTO '.$this->ftable($a).'('.$b[0].') VALUES'.$b[1]);
    // }

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
        $r[1] = '('.implode('),(', $r[1]).')';

        return $r;
    }

    public function safeupdate($table, $kvarray, $whereformat = '' /*, ... */)
    {
        $whereparams = func_get_args();
        array_shift($whereparams); // table
    array_shift($whereparams); // kvarray
    array_shift($whereparams); // whereformat

    /* $whereparams now contains the parameters for the "WHERE" clause. */

        $va_array = array_merge(array_values($kvarray), $whereparams);

        $keynames = $this->safeBuildUpdate($kvarray);
        if (!empty($whereformat)) {
            $whereformat = ' '.$whereformat;
        }

        $query = 'UPDATE '.$this->ftable($table).' SET '.$keynames.$whereformat;

        array_unshift($va_array, $query);

        // syslog(LOG_ERR, "ARRAY: ".print_r($va_array, true));

        return call_user_func_array(array($this, 'safequery'), $va_array);
    }

    // function update($a,$b,$c=''){
    // return $this->query('UPDATE '.$this->ftable($a).' SET '.$this->buildUpdate($b).($c?' '.$c:''));
    // }

    public function safeBuildUpdate($kvarray)
    {
        if (empty($kvarray)) {
            return '';
        }

        return '`'.implode('` = ?, `', array_keys($kvarray)).'` = ?'; /* e.g. if array is a => b; c => c; then result is a = ?, b = ?, where the first " = ?," comes from the implode. */
    }

    public function buildUpdate($a)
    {
        $r = '';
        foreach ($a as $k => $v) {
            $r .= $this->eKey($k).'='.$this->evalue($v).',';
        }

        return substr($r, 0, -1);
    }

    public function safedelete($table, $whereformat /*, ... */)
    {
        $query = 'DELETE FROM '.$this->ftable($table).($whereformat ? ' '.$whereformat : '');

        $va_array = func_get_args();

        array_shift($va_array); /* table */
        array_shift($va_array); /* whereformat */

        // syslog(LOG_ERR, "FMT: $format\n");
        // syslog(LOG_ERR, "SOURCEARRAY: ".print_r($tablenames)."\n");

        array_unshift($va_array, $query); /* Put the format string back. */

        // syslog(LOG_ERR, "NEWFMT: $newformat\n");
        // syslog(LOG_ERR, "OUTARRAY: ".print_r($va_array, true)."\n");

        return call_user_func_array(array($this, 'safequery'), $va_array);
    }

    // function delete($a,$b){
    // return $this->query("DELETE FROM ".$this->ftable($a).($b?" $b":''));
    // }

    public function is_sqli_result($a)
    {
        return $a && is_object($a) && ('mysqli_result' == get_class($a));
    }

    public function row($a = null)
    {
        global $PAGE;
        $a = $a ? $a : $this->lastQuery;
        if ($this->is_sqli_result($a)) {
            $ret = $a ? mysqli_fetch_array($a) : false;
            // syslog(LOG_ERR, "DATA: ".print_r($ret, true)."\n");
            return $ret;
        }
        $ret = $a ? mysql_fetch_array($a) : false;
        // syslog(LOG_ERR, "DATA: ".print_r($ret, true)."\n");
        return $ret;
    }

    /* Only new-style mysqli */
    public function arows($a = null)
    {
        $a = $a ? $a : $this->lastQuery;
        if ($a && $this->is_sqli_result($a)) {
            return $a->fetch_all(MYSQLI_ASSOC);
        }

        return false;
    }

    /* Only new-style mysqli */
    public function rows($a = null)
    {
        $a = $a ? $a : $this->lastQuery;
        if ($a && $this->is_sqli_result($a)) {
            return $a->fetch_all(MYSQLI_BOTH); // Disturbingly, not MYSQL_NUM
        }

        return false;
    }

    public function arow($a = null)
    {
        global $PAGE;
        $a = $a ? $a : $this->lastQuery;
        if ($a) {
            if ($this->is_sqli_result($a)) {
                $q = @mysqli_fetch_assoc($a);
            } else {
                $q = @mysql_fetch_assoc($a);
            }
        } else {
            $q = false;
        }

        return $q;
    }

    public function num_rows($a = null)
    {
        $a = $a ? $a : $this->lastQuery;
        if ($a && $this->is_sqli_result($a)) {
            return $a->num_rows;
        }
        if (function_exists('mysql_num_rows')) {
            return mysql_num_rows($a);
        }

        return 0;
    }

    public function disposeresult($result)
    {
        if (!$result) {
            syslog(LOG_ERR, "NULL RESULT in disposeresult\n".print_r(debug_backtrace(), true));

            return;
        }
        $result->fetch_all();
    }

    /* Warning: nested arrays are *not* supported. */
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

    /* blah ?1 blah ?2 blah ?3 blah */

    /* Note that placeholder_number is indexed from 1. */
    public function safequery_sub_array($query_string, $placeholder_number, $arrlen)
    {
        $arr = explode('?', $query_string, $placeholder_number + 1);
        $last = array_pop($arr);

        $replacement = '('.str_repeat('?, ', ($arrlen) - 1).' ?)';

        // syslog(LOG_ERR, "REPLACEMENT: $replacement\n");

        return implode('?', $arr).$replacement.$last;
    }

    public function safequery($query_string /*, ... */)
    {
        $my_argc = func_num_args();
        $connection = $this->mysqli_connection;
        // syslog(LOG_ERR, "IN SAFEQUERY\n");

        $typestring = '';
        $out_args = array();

        // syslog(LOG_ERR, "QUERYSTRING (PRE): $query_string\n");
        $added_placeholders = 0;
        if ($my_argc > 1) {
            // syslog(LOG_ERR, "HAS ARGS IN SAFEQUERY\n");
            for ($i = 1; $i < $my_argc; ++$i) {
                $value = func_get_arg($i);

                $type = $this->safequery_typeforvalue($value);

                // syslog(LOG_ERR, "Bind: $i initial type $type\n");

                if ('a' == $type) {
                    $type = $this->safequery_array_types($value);

                    $query_string = $this->safequery_sub_array($query_string, $i + $added_placeholders, strlen($type));

                    $added_placeholders += strlen($type) - 1;

                    foreach ($value as $singlevalue) {
                        if (null === $singlevalue) {
                            $singlevalue = '';
                        }
                        array_push($out_args, $singlevalue);
                    }
                } else {
                    if (null === $value) {
                        $value = '';
                    }
                    // syslog(LOG_ERR, "PARM[$i] TYPE: ".gettype($value)."\n");

                    array_push($out_args, $value);
                }
                $typestring .= $type;
            }
            // syslog(LOG_ERR, "TYPES: $typestring, OUT ARGS: ".print_r($out_args, true)."\n");
    // } else {
        // syslog(LOG_ERR, "WARNING: NO ARGS\n");
        }
        array_unshift($out_args, $typestring);

        // syslog(LOG_ERR, "QUERYSTRING: $query_string\n");
        // syslog(LOG_ERR, "QUERYARGS: ".print_r($out_args, true)."\n");

        $stmt = $connection->prepare($query_string);
        // syslog(LOG_ERR, "STMT $stmt\n");
        if (!$stmt) {
            syslog(LOG_ERR, "SAFEQUERY PREPARE FAILED FOR ${query_string}, ".print_r($out_args, true)."\n");

            return;
        }

        $refvalues = $this->refValues($out_args);

        if ($my_argc > 1) {
            /*
            if (!call_user_func_array(array($stmt, "bind_param"), $refvalues)) {
            syslog(LOG_ERR, "QUERYSTRING: $query_string\n");
            syslog(LOG_ERR, "ELEMENTCOUNT: ".strlen($typestring));
            syslog(LOG_ERR, "BINDVARCOUNT: ".(count($refvalues[1])));
            syslog(LOG_ERR, "QUERYARGS: ".print_r($out_args, true)."\n");
            syslog(LOG_ERR, "REFVALUES: ".print_r($refvalues, true)."\n");
            syslog(LOG_ERR, print_r(debug_backtrace(), true));
            } */

            $refclass = new ReflectionClass('mysqli_stmt');
            $method = $refclass->getMethod('bind_param');
            if (!$method->invokeArgs($stmt, $refvalues)) {
                syslog(LOG_ERR, "BIND PARAMETERS FAILED\n");
                syslog(LOG_ERR, "QUERYSTRING: ${query_string}\n");
                syslog(LOG_ERR, 'ELEMENTCOUNT: '.strlen($typestring));
                syslog(LOG_ERR, 'BINDVARCOUNT: '.(count($refvalues[1])));
                syslog(LOG_ERR, 'QUERYARGS: '.print_r($out_args, true)."\n");
                syslog(LOG_ERR, 'REFVALUES: '.print_r($refvalues, true)."\n");
                syslog(LOG_ERR, print_r(debug_backtrace(), true));
            }
        }
        // syslog(LOG_ERR, "SAFEQUERY: $query_string, ".print_r($out_args, true));

        if (!$stmt->execute()) {
            $this->lastfailedstatement = $stmt;

            return;
        }
        if (!$stmt) {
            syslog(LOG_ERR, "Statement is NULL for ${query_string}\n");
        }
        $retval = $stmt->get_result();

        if (!$retval) {
            if (!preg_match('/^\\s*(UPDATE|DELETE|INSERT)\\s/i', $query_string)) {
                /* This is normal for a non-SELECT query. */
                syslog(LOG_ERR, "Result is NULL for ${query_string}\n");
            }
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

    public function query($a, $over = 1)
    {
        global $USER;
        if ($this->debugMode) {
            return print $a.'<br />';
        }
        if (!$this->connected) {
            return false;
        }
        if (!$this->nolog) {
            $this->queryList[] = $a;
            $time = microtime(true);
        }
        $query = mysql_query($a);
        if (!$this->nolog) {
            $this->queryRuntime[] = round(microtime(true) - $time, 5);
        }
        if ($over) {
            $this->lastQuery = $query;
        }

        return $query;
    }

    public function ekey($key)
    {
        return '`'.$this->escape($key).'`';
    }

    /* Like evalue, but does not quote strings.  For use with safequery(). */
    public function basicvalue($value, $forsprintf = 0)
    {
        if (is_array($value)) {
            return $value[0];
        }
        if (is_null($value)) {
            return 'NULL';
        }

        return $value;
    }

    public function evalue($value, $forsprintf = 0)
    {
        if (is_array($value)) {
            $value = $value[0];
        } elseif (is_null($value)) {
            $value = 'NULL';
        } else {
            $value = is_integer($value) ? $value : '\''.$this->escape(($forsprintf ? str_replace('%', '%%', $value) : $value)).'\'';
        }

        return $value;
    }

    public function escape($a)
    {
        return function_exists('mysql_real_escape_string') && $this->connected ? mysql_real_escape_string($a) : addslashes($a);
    }

    public function safespecial(/* $format, $tablenames, ... */)
    {
        $va_array = func_get_args();

        $format = array_shift($va_array); /* Format */
        $tablenames = array_shift($va_array); /* Table names */

        // syslog(LOG_ERR, "FMT: $format\n");
        // syslog(LOG_ERR, "SOURCEARRAY: ".print_r($tablenames)."\n");

        $tempformat = str_replace('%t', '%s', $format);

        if (!$tablenames) {
            syslog(LOG_ERR, "NO TABLE NAMES\n".print_r(debug_backtrace(), true));
        }

        $newformat = vsprintf($tempformat, array_map(array($this, 'ftable'), $tablenames));

        array_unshift($va_array, $newformat); /* Put the format string back. */

        // syslog(LOG_ERR, "NEWFMT: $newformat\n");
        // syslog(LOG_ERR, "OUTARRAY: ".print_r($va_array, true)."\n");

        return call_user_func_array(array($this, 'safequery'), $va_array);
    }

    // function special(){
    // $a=func_get_args();
    // $b=array_shift($a);
    // return $this->query(vsprintf(str_replace("%t",$this->ftable("%s"),$b),$a));
    // }

    public function getUsersOnline()
    {
        global $CFG,$USER,$SESS;
        $idletimeout = time() - $CFG['timetoidle'];
        $r = array('guestcount' => 0);
        if (!$this->usersOnlineCache) {
            $result = $this->safespecial("SELECT a.id,a.uid,a.location,a.location_verbose,a.hide,a.is_bot,b.display_name AS name,b.group_id,concat(b.dob_month,' ',b.dob_day) `dob`,a.last_action,a.last_update FROM %t AS a
LEFT JOIN %t AS b ON a.uid=b.id
WHERE last_update>=?
 ORDER BY last_action DESC",
    array('session', 'members' /* ,"member_groups" */),
    (time() - $CFG['timetologout']));
            $today = date('n j');
            while ($f = $this->arow($result)) {
                if ($f['hide']) {
                    if (2 != $USER['group_id']) {
                        continue;
                    }
                    $f['name'] = '* '.$f['name'];
                }
                $f['birthday'] = ($f['dob'] == $today ? 1 : 0);
                $f['status'] = ($f['last_action'] < $idletimeout ? 'idle' : 'active');
                if ($f['is_bot']) {
                    $f['name'] = $f['id'];
                    $f['uid'] = $f['id'];
                }
                unset($f['id'], $f['dob']);
                if ($f['uid']) {
                    if (!$r[$f['uid']]) {
                        $r[$f['uid']] = $f;
                    }
                } else {
                    ++$r['guestcount'];
                }
            }

            /*since we update the session data at the END of the page, we'll want to include
              the user in the usersonline */
            if ($USER && $r[$USER['id']]) {
                $r[$USER['id']] = array(
                    'uid' => $USER['id'],
                    'group_id' => $USER['group_id'],
                    'last_action' => $SESS->last_action,
                    'last_update' => $SESS->last_update,
                    'name' => ($SESS->hide ? '* ' : '').$USER['display_name'],
                    'status' => ($SESS->last_action < $idletimeout ? 'idle' : 'active'),
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
        $result = $this->safeselect('lp_uid,lp_date,id,title', 'topics', 'WHERE fid=? ORDER BY lp_date DESC LIMIT 1', $fid);
        $d = $this->row($result);
        $this->disposeresult($result);
        $this->safeupdate('forums', array('lp_uid' => $d['lp_uid'], 'lp_date' => $d['lp_date'], 'lp_tid' => $d['id'], 'lp_topic' => $d['title']), 'WHERE id=?', $fid);
    }

    public function fixAllForumLastPosts()
    {
        $query = $this->safeselect('id', 'forums');
        while ($fid = $this->row($query)) {
            $this->fixForumLastPost($fid);
        }
    }

    public function getRatingNiblets()
    {
        if ($this->ratingNiblets) {
            return $this->ratingNiblets;
        }
        $result = $this->safeselect('*', 'ratingniblets');
        $r = array();
        while ($f = $this->row($result)) {
            $r[$f['id']] = array('img' => $f['img'], 'title' => $f['title']);
        }

        return $this->ratingNiblets = $r;
    }

    public function debug()
    {
        return '<div>'.implode('<br />', $this->queryList).'</div>';
        $this->queryList = array();
    }
}
