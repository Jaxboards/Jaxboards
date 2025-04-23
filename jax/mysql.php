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
use function date;
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
use function password_hash;
use function password_needs_rehash;
use function password_verify;
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
use const PASSWORD_DEFAULT;
use const PHP_EOL;

final class MySQL
{
    public $lastQuery;

    public $debugMode = false;

    private $queryList = [];

    private $mysqli_connection = false;

    private $prefix = '';

    private $usersOnlineCache = '';

    private $ratingNiblets = [];

    private $db = '';

    public function __construct(
        private Config $config,
        private IPAddress $ipAddress,
    ) {}

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
        $this->db = $database;

        return !$this->mysqli_connection->connect_errno;
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

    public function buildInsert($a): array
    {
        $r = [[], [[]]];
        if (!isset($a[0]) || !is_array($a[0])) {
            $a = [$a];
        }

        foreach ($a as $k => $v) {
            ksort($v);
            foreach ($v as $k2 => $v2) {
                if (is_string($v2) && mb_check_encoding($v2) !== 'UTF-8') {
                    $v2 = mb_convert_encoding($v2, 'UTF-8', 'ISO-8859-1');
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

    public function buildUpdate($a): string
    {
        $r = '';
        foreach ($a as $k => $v) {
            $r .= $this->eKey($k) . '=' . $this->evalue($v) . ',';
        }

        return mb_substr($r, 0, -1);
    }

    public function safedelete($table, $whereformat, ...$vars): mixed
    {
        $query = 'DELETE FROM ' . $this->ftable($table)
            . ($whereformat ? ' ' . $whereformat : '');

        // Put the format string back.
        return $this->safequery($query, ...$vars);
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
        $query_string,
        $placeholder_number,
        $arrlen,
    ): string {
        $arr = explode('?', (string) $query_string, $placeholder_number + 1);
        $last = array_pop($arr);
        $replacement = '';

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
        if ($this->debugMode) {
            $this->queryList[] = $query_string;
        }

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

    /*
        The getSess and getUser functions both return the
        session/user data respectively
        if not found in the database, getSess inserts a blank Sess row,
        while getUser returns false.
     */
    public function getUser($uid = false, $pass = false)
    {
        static $userData = false;

        if ($userData) {
            return $userData;
        }

        if (!$uid) {
            return false;
        }

        $result = $this->safeselect(
            [
                'about',
                'avatar',
                'birthdate',
                'contact_aim',
                'contact_bluesky',
                'contact_discord',
                'contact_gtalk',
                'contact_msn',
                'contact_skype',
                'contact_steam',
                'contact_twitter',
                'contact_yim',
                'contact_youtube',
                'display_name',
                'email_settings',
                'email',
                'enemies',
                'friends',
                'full_name',
                'gender',
                'group_id',
                'id',
                'ip',
                'location',
                '`mod`',
                'name',
                'notify_pm',
                'notify_postinmytopic',
                'notify_postinsubscribedtopic',
                'nowordfilter',
                'pass',
                'posts',
                'sig',
                'skin_id',
                'sound_im',
                'sound_pm',
                'sound_postinmytopic',
                'sound_postinsubscribedtopic',
                'sound_shout',
                'ucpnotepad',
                'usertitle',
                'website',
                'wysiwyg',
                "CONCAT(MONTH(`birthdate`),' ',DAY(`birthdate`)) as `birthday`",
                'DAY(`birthdate`) AS `dob_day`',
                'MONTH(`birthdate`) AS `dob_month`',
                'UNIX_TIMESTAMP(`join_date`) AS `join_date`',
                'UNIX_TIMESTAMP(`last_visit`) AS `last_visit`',
                'YEAR(`birthdate`) AS `dob_year`',
            ],
            'members',
            'WHERE `id`=?',
            $this->basicvalue($uid),
        );
        $user = $this->arow($result);
        $this->disposeresult($result);

        if (empty($user)) {
            return $userData = false;
        }

        if ($this->ipAddress->isBanned()) {
            $userData['group_id'] = 4;
        }

        $user['birthday'] = (date('n j') === $user['birthday'] ? 1 : 0);

        // Password parsing.
        if ($pass !== false) {
            $verifiedPassword = password_verify((string) $pass, (string) $user['pass']);

            if (!$verifiedPassword) {
                return $userData = false;
            }

            $needsRehash = password_needs_rehash(
                $user['pass'],
                PASSWORD_DEFAULT,
            );

            if ($needsRehash) {
                $new_hash = password_hash((string) $pass, PASSWORD_DEFAULT);
                // Add the new hash.
                $this->safeupdate(
                    'members',
                    [
                        'pass' => $new_hash,
                    ],
                    'WHERE `id` = ?',
                    $user['id'],
                );
            }

            unset($user['pass']);
        }

        $userData = $user;

        return $userData;
    }

    public function getPerms($groupId = null)
    {
        static $userPerms = null;

        if ($groupId === null && $userPerms !== null) {
            return $userPerms;
        }

        $userData = $this->getUser();

        if ($groupId === null && $userData) {
            $groupId = $userData['group_id'];
        }


        $result = $this->safeselect(
            <<<'EOT'
                `can_access_acp`,
                `can_add_comments`,
                `can_attach`,
                `can_delete_comments`,
                `can_delete_own_posts`,
                `can_delete_own_shouts`,
                `can_delete_own_topics`,
                `can_delete_shouts`,
                `can_edit_posts`,
                `can_edit_topics`,
                `can_im`,
                `can_karma`,
                `can_lock_own_topics`,
                `can_moderate`,
                `can_override_locked_topics`,
                `can_pm`,
                `can_poll`,
                `can_post_topics`,
                `can_post`,
                `can_shout`,
                `can_use_sigs`,
                `can_view_board`,
                `can_view_fullprofile`,
                `can_view_offline_board`,
                `can_view_shoutbox`,
                `can_view_stats`,
                `flood_control`,
                `icon`,
                `id`,
                `legend`,
                `title`
                EOT
            ,
            'member_groups',
            'WHERE `id`=?',
            $groupId ?? 3,
        );
        $retval = $this->arow($result);
        $userPerms = $retval;
        $this->disposeresult($result);

        return $userPerms;
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
        global $USER,$SESS;
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
                    'last_action' => gmdate('Y-m-d H:i:s', (int) ($SESS->last_action ?? 0)),
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
