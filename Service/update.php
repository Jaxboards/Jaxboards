<?php

/**
 * Updater to update existing jaxboards to new MySQL standards.
 *
 * PHP Version 5.3.0
 *
 * @category Jaxboards
 * @package  Jaxboards
 *
 * @author  World's Tallest Ladder <wtl420@users.noreply.github.com>
 * @license MIT <https://opensource.org/licenses/MIT>
 *
 * @link https://github.com/Jaxboards/Jaxboards Jaxboards Github repo
 */

if (!defined('JAXBOARDS_ROOT')) {
    define('JAXBOARDS_ROOT', dirname(__DIR__));
}
if (!defined('SERVICE_ROOT')) {
    define('SERVICE_ROOT', __DIR__);
}

if (file_exists(SERVICE_ROOT . '/update.lock')) {
    die('Update lock file found! Please remove if you wish to install.');
}

// Load mysql classes.
require_once JAXBOARDS_ROOT . '/inc/classes/mysql.php';
$DB = new MySQL();

// Get CFG variable.
require_once JAXBOARDS_ROOT . '/config.php';

$connected = $DB->connect(
    $CFG['sql_host'],
    $CFG['sql_username'],
    $CFG['sql_password'],
    $CFG['sql_db']
);

if (!$connected) {
    die('There was an error connecting to the MySQL database.');
}

$DB->safequery('SET foreign_key_checks = 0;');

$boards = array();

if ($CFG['service']) {
    $queries = array();
    // Copy over `directory` table from `jaxboards_service`
    // if it exists.
    $serviceDB = true;
    try {
        $DB->select_db('jaxboards_service');
    } catch (Exception $e) {
        $serviceDB = false;
    }
    if ('jaxboards_service' !== $DB->db) {
        $serviceDB = false;
    }
    if ($serviceDB) {
        $result = $DB->safequery("SHOW TABLES LIKE '%%'");
        $tables = $DB->rows($result);
        foreach ($tables as $table) {
            $table = $table[0];
            if (mb_strpos($table, 'blueprint_') === false) {
                // Ignore blueprint tables.
                $result = $DB->safespecial(
                    'SHOW CREATE TABLE %t',
                    array($table)
                );
                $createTableStatement = $DB->row($result);
                if ($createTableStatement) {
                    $queries[] = "DROP TABLE IF EXISTS `{$table}`;";
                    $queries[] = array_pop($createTableStatement) . ';';
                    $DB->disposeresult($result);
                    $select = $DB->safeselect('*', $table);
                    while ($row = $DB->arow($select)) {
                        $insert = $DB->buildInsert($row);
                        $columns = $insert[0];
                        $values = $insert[1];
                        $queries[] = "INSERT INTO `{$table}` ({$columns}) " .
                            "VALUES {$values};";
                    }
                }
                $DB->disposeresult($result);
            }
        }
    }
    $DB->select_db($CFG['sql_db']);

    // Queries to update directory table.
    $queries[] = <<<'EOT'
UPDATE `directory` SET `registrar_ip` = 0 WHERE `registrar_ip` IS NULL;
EOT;
    $queries[] = <<<'EOT'
UPDATE `directory` SET `date` = 0 WHERE `date` IS NULL;
EOT;
    $queries[] = <<<'EOT'
DELETE FROM `directory` WHERE `boardname` IS NULL;
EOT;
    $queries[] = <<<'EOT'
UPDATE `directory` SET `referral` = '' WHERE `referral` IS NULL;
EOT;
    $queries[] = <<<'EOT'
ALTER TABLE `directory`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `registrar_email` `registrar_email` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `id`,
    CHANGE `registrar_ip` `registrar_ip` varbinary(16) NOT NULL DEFAULT '' AFTER `registrar_email`,
    CHANGE `boardname` `boardname` varchar(30) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `date`,
    CHANGE `referral` `referral` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `boardname`,
    CHANGE `show` `show` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `referral`,
    CHANGE `description` `description` varchar(500) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `show`,
    ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
EOT;
    $queries[] = <<<'EOT'
ALTER TABLE `banlist`
    CHANGE `ip` `ip` varbinary(16) NOT NULL FIRST,
    ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
EOT;
    foreach ($queries as $query) {
        $result = $DB->safequery($query);
        $DB->disposeresult($result);
    }

    // Check if date needs updates.
    $result = $DB->safequery(
        <<<'EOT'
SELECT
    TIMESTAMPDIFF(DAY, DATE(MAX(`date`)), DATE(MAX(`date`))) as `date_check`
    FROM `directory`
    LIMIT 1;
EOT
    );
    $row = $DB->arow($result);
    $DB->disposeresult($result);
    if (null === $row['date_check']) {
        $queries = array(
            <<<'EOT'
ALTER TABLE `directory`
    CHANGE `date` `date_tmp` int(11) unsigned NOT NULL AFTER `registrar_ip`,
    ADD `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `date_tmp`;
EOT
            ,
            <<<'EOT'
UPDATE `directory`
    SET `date`=FROM_UNIXTIME(COALESCE(`date_tmp`, 0));
EOT
            ,
            <<<'EOT'
ALTER TABLE `directory` DROP `date_tmp`;
EOT
            ,
        );
        foreach ($queries as $query) {
            $result = $DB->safequery($query);
            $DB->disposeresult($DB);
        }
    }

    // Check if IP field needs update.
    $result = $DB->safequery(
        <<<'EOT'
SELECT INET6_NTOA(MAX(`registrar_ip`)) as `ip_check`
    FROM `directory`
    LIMIT 1;
EOT
    );
    $row = $DB->arow($result);
    $DB->disposeResult($result);
    if (null === $row['ip_check']) {
        $result = $DB->safequery(
            <<<'EOT'
UPDATE `directory`
    SET `registrar_ip`=COALESCE(
        INET6_ATON(INET_NTOA(`registrar_ip`)),
        INET6_ATON(INET_NTOA(0))
    );
EOT
        );
        $DB->disposeresult($result);
    }
    // Check if IP field needs update.
    $result = $DB->safequery(
        <<<'EOT'
SELECT INET6_NTOA(MAX(`ip`)) as `ip_check`
    FROM `banlist`
    LIMIT 1;
EOT
    );
    $row = $DB->arow($result);
    $DB->disposeResult($result);
    if (null === $row['ip_check']) {
        $queries = array(
            <<<'EOT'
DELETE FROM `banlist`
WHERE INET6_ATON(`ip`) IS NULL;
EOT
            ,
            <<<'EOT'
UPDATE `banlist`
    SET `ip`=INET6_ATON(`ip`);
EOT
        ,
        );
        foreach ($queries as $query) {
            $result = $DB->safequery(
                $query
            );
            $DB->disposeresult($result);
        }
    }

    // Check if we need to create indexes/foriegn keys.
    $result = $DB->safequery('SHOW CREATE TABLE `directory`;');
    $createTableStatement = $DB->row($result);
    $createTableStatement = array_pop($createTableStatement);
    $DB->disposeresult($result);
    if (!preg_match("/KEY\s+`boardname`/i", $createTableStatement)) {
        $result = $DB->safequery(
            <<<'EOT'
ALTER TABLE `directory`
    ADD INDEX `boardname` (`boardname`);
EOT
        );
        $DB->disposeresult($result);
    }
    $result = $DB->safequery('SHOW CREATE TABLE `banlist`;');
    $createTableStatement = $DB->row($result);
    $createTableStatement = array_pop($createTableStatement);
    $DB->disposeresult($result);
    if (!preg_match("/UNIQUE\s+`ip`/i", $createTableStatement)) {
        $result = $DB->safequery(
            <<<'EOT'
ALTER TABLE `banlist`
    ADD UNIQUE `banlist` (`banlist`);
EOT
        );
        $DB->disposeresult($result);
    }


    $result = $DB->safequery('SELECT `boardname` AS `board` FROM `directory`;');
    while ($row = $DB->arow($result)) {
        $boards[] = $row['board'];
    }
    $DB->disposeresult($result);
} else {
    $boards[] = $CFG['prefix'];
}

// Data to fix.
$deleteIfFalse = array(
    'activity' => array(
        'uid',
    ),
    'files' => array(
        'name',
        'hash',
    ),
    'members' => array(
        'name',
    ),
    'member_groups' => array(
        'title',
    ),
    'messages' => array(
        'title',
        'message',
    ),
    'pages' => array(
        'act',
    ),
    'posts' => array(
        'post',
        'tid',
    ),
    'profile_comments' => array(
        'to',
        'from',
        'comment',
    ),
    'ratingniblets' => array(
        'img',
        'title',
    ),
    'reports' => array(
        'reason',
        'status',
    ),
    'shouts' => array(
        'shout',
    ),
    'skins' => array(
        'title',
    ),
    'textrules' => array(
        'type',
        'needle',
        'replacement',
    ),
    'topics' => array(
        'title',
    ),
);
$nullToString = array(
    'activity' => array(
        'arg1',
        'arg2',
    ),
    'forums' => array(
        'subtitle',
        'lp_topic',
        'path',
        'redirect',
        'perms',
        'mods',
    ),
    'logs' => array(
        'data',
    ),
    'members' => array(
        'pass',
        'email',
        'sig',
        'avatar',
        'usertitle',
        'contact_skype',
        'contact_yim',
        'contact_msn',
        'contact_gtalk',
        'contact_aim',
        'website',
        'about',
        'display_name',
        'full_name',
        'contact_steam',
        'location',
        'friends',
        'enemies',
        'ucpnotepad',
        'contact_twitter',
    ),
    'member_groups' => array(
        'icon',
    ),
    'pages' => array(
        'page',
    ),
    'posts' => array(
        'rating',
    ),
    'session' => array(
        'vars',
        'runonce',
        'location',
        'users_online_cache',
        'buddy_list_cache',
        'location_verbose',
        'useragent',
        'forumsread',
        'topicsread',
    ),
    'skins' => array(
        'wrapper',
    ),
    'topics' => array(
        'subtitle',
        'poll_choices',
        'poll_results',
        'poll_q',
        'polL_type',
        'summary',
    ),
);
$nullToInt = array(
    'categories' => array(
        'order',
    ),
    'files' => array(
        'size',
        'ip',
    ),
    'forums' => array(
        'show_sub',
        'topics',
        'posts',
        'order',
        'orderby',
        'nocount',
        'redirects',
        'trashcan',
        'show_ledby',
    ),
    'logs' => array(
        'code',
        'ip',
        'action',
    ),
    'members' => array(
        'posts',
        'sound_shout',
        'sound_im',
        'sound_pm',
        'sound_postinmytopic',
        'sound_postinsubscribedtopic',
        'notify_pm',
        'notify_postinmytopic',
        'notify_postinsubscribedtopic',
        'email_settings',
        'nowordfilter',
        'ip',
        'mod',
        'wysiwyg',
    ),
    'member_groups' => array(
        'can_post',
        'can_edit_posts',
        'can_post_topics',
        'can_edit_topics',
        'can_add_comments',
        'can_delete_comments',
        'can_view_board',
        'can_view_offline_board',
        'flood_control',
        'can_override_locked_topics',
        'can_shout',
        'can_moderate',
        'can_delete_shouts',
        'can_delete_own_shouts',
        'can_karma',
        'can_im',
        'can_pm',
        'can_lock_own_topics',
        'can_delete_own_topics',
        'can_use_sigs',
        'can_attach',
        'can_delete_own_posts',
        'can_poll',
        'can_access_acp',
        'can_view_shoutbox',
        'can_view_stats',
        'legend',
    ),
    'messages' => array(
        'read',
        'del_recipient',
        'del_sender',
        'flag',
    ),
    'posts' => array(
        'showsig',
        'showemotes',
        'newtopic',
        'ip',
    ),
    'session' => array(
        'ip',
        'is_bot',
        'hide',
    ),
    'shouts' => array(
        'ip',
    ),
    'skins' => array(
        'using',
        'custom',
        'default',
        'hidden',
    ),
    'stats' => array(
        'posts',
        'topics',
        'members',
        'most_members',
        'most_members_day',
    ),
    'textrules' => array(
        'enabled',
    ),
    'topics' => array(
        'replies',
        'views',
        'pinned',
        'locked',
        'cal_event',
    ),
);
$intToNull = array(
    'activity' => array(
        'affected_uid',
        'tid',
        'pid',
    ),
    'files' => array(
        'uid',
    ),
    'forums' => array(
        'cat_id',
        'lp_uid',
        'lp_tid',
    ),
    'logs' => array(
        'uid',
    ),
    'members' => array(
        'group_id',
        'skin_id',
    ),
    'messages' => array(
        'to',
        'from',
    ),
    'posts' => array(
        'auth_id',
        'editby',
    ),
    'reports' => array(
        'reporter',
    ),
    'session' => array(
        'uid',
    ),
    'shouts' => array(
        'uid',
    ),
    'stats' => array(
        'last_register',
    ),
    'topics' => array(
        'lp_uid',
        'fid',
        'auth_id',
        'op',
    ),
);
// Order matters here, don't re-arrange unless you know what you're doing.
$fixForeignKeyRelations = array(
    'members' => array(
        'group_id' => array(
            'table' => 'member_groups',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_members`
ADD FOREIGN KEY (`group_id`) REFERENCES `blueprint_member_groups` (`id`)
    ON DELETE SET NULL;
EOT
            ,
        ),
        'skin_id' => array(
            'table' => 'skins',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_members`
ADD FOREIGN KEY (`skin_id`) REFERENCES `blueprint_skins` (`id`)
    ON DELETE SET NULL;
EOT
            ,
        ),
    ),
    'forums' => array(
        'cat_id' => array(
            'table' => 'categories',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_forums`
ADD FOREIGN KEY (`cat_id`) REFERENCES `blueprint_categories` (`id`)
    ON DELETE SET NULL;
EOT
            ,
        ),
        'lp_uid' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_forums`
ADD FOREIGN KEY (`lp_uid`) REFERENCES `blueprint_members` (`id`)
     ON DELETE SET NULL;
EOT
            ,
        ),
        'lp_tid' => array(
            'table' => 'topics',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_forums`
ADD FOREIGN KEY (`lp_tid`) REFERENCES `blueprint_topics` (`id`)
    ON DELETE SET NULL;
EOT
            ,
        ),
    ),
    'topics' => array(
        'auth_id' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_topics`
ADD FOREIGN KEY (`auth_id`) REFERENCES `blueprint_members` (`id`)
    ON DELETE SET NULL;
EOT
            ,
        ),
        'fid' => array(
            'table' => 'forums',
            'column' => 'id',
            'type' => 'delete',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_topics`
ADD FOREIGN KEY (`fid`) REFERENCES `blueprint_forums` (`id`) ON DELETE CASCADE;
EOT
            ,
        ),
        'lp_uid' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_topics`
ADD FOREIGN KEY (`lp_uid`) REFERENCES `blueprint_members` (`id`)
    ON DELETE SET NULL;
EOT
            ,
        ),
        'op' => array(
            'table' => 'posts',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_topics`
ADD FOREIGN KEY (`op`) REFERENCES `blueprint_posts` (`id`) ON DELETE SET NULL;
EOT
            ,
        ),
    ),
    'posts' => array(
        'auth_id' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_posts`
ADD FOREIGN KEY (`auth_id`) REFERENCES `blueprint_members` (`id`)
    ON DELETE SET NULL;
EOT
            ,
        ),
        'tid' => array(
            'table' => 'topics',
            'column' => 'id',
            'type' => 'delete',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_posts`
ADD FOREIGN KEY (`tid`) REFERENCES `blueprint_topics` (`id`) ON DELETE CASCADE;
EOT
            ,
        ),
    ),
    'logs' => array(
        'uid' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_logs`
ADD FOREIGN KEY (`uid`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL;
EOT
            ,
        ),
    ),
    'messages' => array(
        'from' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_messages`
ADD FOREIGN KEY (`from`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL;
EOT
            ,
        ),
        'to' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_messages`
ADD FOREIGN KEY (`to`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL;
EOT
            ,
        ),
    ),
    'profile_comments' => array(
        'from' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'delete',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_profile_comments`
ADD FOREIGN KEY (`from`) REFERENCES `blueprint_members` (`id`) ON DELETE CASCADE;
EOT
            ,
        ),
        'to' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'delete',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_profile_comments`
ADD FOREIGN KEY (`to`) REFERENCES `blueprint_members` (`id`) ON DELETE CASCADE;
EOT
            ,
        ),
    ),
    'reports' => array(
        'reporter' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_reports`
ADD FOREIGN KEY (`reporter`) REFERENCES `blueprint_members` (`id`)
    ON DELETE SET NULL;
EOT
            ,
        ),
    ),
    'session' => array(
        'uid' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'delete',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_session`
ADD FOREIGN KEY (`uid`) REFERENCES `blueprint_members` (`id`) ON DELETE CASCADE;
EOT
            ,
        ),
    ),
    'shouts' => array(
        'uid' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_shouts`
ADD FOREIGN KEY (`uid`) REFERENCES `blueprint_members` (`id`) ON DELETE CASCADE;
EOT
            ,
        ),
    ),
    'stats' => array(
        'last_register' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_stats`
ADD FOREIGN KEY (`last_register`) REFERENCES `blueprint_members` (`id`)
    ON DELETE SET NULL;
EOT
            ,
        ),
    ),
    'files' => array(
        'uid' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'null',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_files`
ADD FOREIGN KEY (`uid`) REFERENCES `blueprint_members` (`id`) ON DELETE SET NULL;
EOT
            ,
        ),
    ),
    'activity' => array(
        'affected_uid' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'delete',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_activity`
ADD FOREIGN KEY (`affected_uid`) REFERENCES `blueprint_members` (`id`)
    ON DELETE CASCADE;
EOT
            ,
        ),
        'pid' => array(
            'table' => 'posts',
            'column' => 'id',
            'type' => 'delete',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_activity`
ADD FOREIGN KEY (`pid`) REFERENCES `blueprint_posts` (`id`) ON DELETE CASCADE;
EOT
            ,
        ),
        'tid' => array(
            'table' => 'topics',
            'column' => 'id',
            'type' => 'delete',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_activity`
ADD FOREIGN KEY (`tid`) REFERENCES `blueprint_topics` (`id`) ON DELETE CASCADE;
EOT
            ,
        ),
        'uid' => array(
            'table' => 'members',
            'column' => 'id',
            'type' => 'delete',
            'query' => <<<'EOT'
ALTER TABLE `blueprint_activity`
ADD FOREIGN KEY (`uid`) REFERENCES `blueprint_members` (`id`) ON DELETE CASCADE;
EOT
            ,
        ),
    ),
);
$dateFixes = array(
    'activity' => array(
        'date' => array(
            'new' => 'date',
            'pos' => 'uid',
        ),
    ),
    'forums' => array(
        'lp_date' => array(
            'new' => 'lp_date',
            'pos' => 'lp_uid',
        ),
    ),
    'members' => array(
        'join_date' => array(
            'new' => 'join_date',
            'pos' => 'usertitle',
        ),
        'last_visit' => array(
            'new' => 'last_visit',
            'pos' => 'join_date',
        ),
    ),
    'messages' => array(
        'date' => array(
            'new' => 'date',
            'pos' => 'read',
        ),
    ),
    'posts' => array(
        'date' => array(
            'new' => 'date',
            'pos' => 'post',
        ),
        'editdate' => array(
            'new' => 'edit_date',
            'pos' => 'ip',
        ),
    ),
    'profile_comments' => array(
        'date' => array(
            'new' => 'date',
            'pos' => 'comment',
        ),
    ),
    'reports' => array(
        'time' => array(
            'new' => 'date',
            'pos' => 'reason',
        ),
    ),
    'reports' => array(
        'time' => array(
            'new' => 'date',
            'pos' => 'reason',
        ),
    ),
    'session' => array(
        'last_update' => array(
            'new' => 'last_update',
            'pos' => 'vars',
        ),
        'last_action' => array(
            'new' => 'last_action',
            'pos' => 'last_update',
        ),
        'readtime' => array(
            'new' => 'read_date',
            'pos' => 'topicsread',
        ),
    ),
    'shouts' => array(
        'timestamp' => array(
            'new' => 'date',
            'pos' => 'shout',
        ),
    ),
    'topics' => array(
        'date' => array(
            'new' => 'date',
            'pos' => 'locked',
        ),
        'lp_date' => array(
            'new' => 'lp_date',
            'pos' => 'lp_uid',
        ),
    ),
);

$ipFixes = array(
    'files' => 'ip',
    'logs' => 'ip',
    'members' => 'ip',
    'posts' => 'ip',
    'session' => 'ip',
    'shouts' => 'ip',
);


// Update fields.
foreach ($boards as $board) {
    $DB->prefix($board . '_');
    foreach ($deleteIfFalse as $table => $columns) {
        $table = $DB->ftable($table);
        foreach ($columns as $column) {
            $result = $DB->safequery(
                <<<EOT
DELETE FROM {$table}
WHERE `{$column}` IS NULL
OR `{$column}` = '0'
OR `{$column}` = '';
EOT
            );
            $DB->disposeresult($result);
        }
    }

    foreach ($nullToString as $table => $columns) {
        $table = $DB->ftable($table);
        foreach ($columns as $column) {
            $result = $DB->safequery(
                <<<EOT
UPDATE {$table}
SET `{$column}` = ''
WHERE `{$column}` IS NULL;
EOT
            );
            $DB->disposeresult($result);
        }
    }
    foreach ($nullToInt as $table => $columns) {
        $table = $DB->ftable($table);
        foreach ($columns as $column) {
            $result = $DB->safequery(
                <<<EOT
UPDATE {$table}
SET `{$column}` = '0'
WHERE `{$column}` IS NULL;
EOT
            );
            $DB->disposeresult($result);
        }
    }

    // Update tables.
    // Fix gender column.
    $table = $DB->ftable('members');
    $result = $DB->safequery("SHOW COLUMNS FROM {$table} LIKE 'sex'");
    if ($DB->arow($result)) {
        // Need to allow all options first (including null).
        $fixresult = $DB->safequery(
            <<<EOT
        ALTER TABLE {$table}
            CHANGE `sex` `sex` enum('','male','female','other')
                COLLATE 'utf8mb4_unicode_ci'
                NULL DEFAULT '' AFTER `location`;
EOT
        );
        $DB->disposeresult($fixresult);
        // Remove all null values.
        $result = $DB->safequery(
            <<<EOT
UPDATE {$table}
SET `sex` = ''
WHERE `sex` IS NULL;
EOT
        );
        $DB->disposeresult($result);
        // Finally we can fix the table.
        $fixresult = $DB->safequery(
            <<<EOT
        ALTER TABLE {$table}
            CHANGE `sex` `gender` enum('','male','female','other')
                COLLATE 'utf8mb4_unicode_ci'
                NOT NULL DEFAULT '' AFTER `location`;
EOT
        );
        $DB->disposeresult($fixresult);
    }
    $DB->disposeresult($result);

    $queries = array(
        <<<'EOT'
ALTER TABLE `blueprint_activity`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `type` `type` enum('new_post','new_topic','buddy_add','buddy_block',
        'buddy_status','profile_name_change','profile_comment')
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `id`,
    CHANGE `arg1` `arg1` varchar(255) COLLATE 'utf8mb4_unicode_ci'
        NOT NULL DEFAULT '' AFTER `type`,
    CHANGE `uid` `uid` int(11) unsigned NOT NULL AFTER `arg1`,
    CHANGE `affected_uid` `affected_uid` int(11) unsigned NULL AFTER `uid`,
    CHANGE `tid` `tid` int(11) unsigned NULL AFTER `affected_uid`,
    CHANGE `pid` `pid` int(11) unsigned NULL AFTER `tid`,
    CHANGE `arg2` `arg2` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `pid`,
    COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_categories`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `title` `title` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `id`,
    CHANGE `order` `order` int(11) unsigned NOT NULL DEFAULT '0' AFTER `title`,
    COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_files`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `name` `name` varchar(100)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `id`,
    CHANGE `hash` `hash` varchar(191)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `name`,
    CHANGE `uid` `uid` int(11) unsigned NULL AFTER `hash`,
    CHANGE `size` `size` int(11) unsigned NOT NULL DEFAULT '0' AFTER `uid`,
    CHANGE `downloads` `downloads` int(11) unsigned
        NOT NULL DEFAULT '0' AFTER `size`,
    CHANGE `ip` `ip` varbinary(16) NOT NULL DEFAULT '' AFTER `downloads`,
    COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_forums`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `cat_id` `cat_id` int(11) unsigned NULL AFTER `id`,
    CHANGE `title` `title` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `cat_id`,
    CHANGE `subtitle` `subtitle` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `title`,
    CHANGE `lp_uid` `lp_uid` int(11) unsigned NULL AFTER `subtitle`,
    CHANGE `lp_tid` `lp_tid` int(11) unsigned NULL AFTER `lp_uid`,
    CHANGE `lp_topic` `lp_topic` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `lp_tid`,
    CHANGE `path` `path` varchar(100)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `lp_topic`,
    CHANGE `show_sub` `show_sub` tinyint(3) unsigned
        NOT NULL DEFAULT '0' AFTER `path`,
    CHANGE `redirect` `redirect` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `show_sub`,
    CHANGE `topics` `topics` int(11) unsigned
        NOT NULL DEFAULT '0' AFTER `redirect`,
    CHANGE `posts` `posts` int(11) unsigned
        NOT NULL DEFAULT '0' AFTER `topics`,
    CHANGE `order` `order` int(11) unsigned NOT NULL DEFAULT '0' AFTER `posts`,
    CHANGE `perms` `perms` varbinary(48) NOT NULL DEFAULT '' AFTER `order`,
    CHANGE `orderby` `orderby` tinyint(3) unsigned
        NOT NULL DEFAULT '0' AFTER `perms`,
    CHANGE `nocount` `nocount` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `orderby`,
    CHANGE `redirects` `redirects` int(11) unsigned
        NOT NULL DEFAULT '0' AFTER `nocount`,
    CHANGE `trashcan` `trashcan` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `redirects`,
    CHANGE `mods` `mods` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `trashcan`,
    CHANGE `show_ledby` `show_ledby` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `mods`,
    COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_logs`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `code` `code` tinyint(3) unsigned NOT NULL DEFAULT '0' AFTER `id`,
    CHANGE `ip` `ip` varbinary(16) NOT NULL DEFAULT '' AFTER `code`,
    CHANGE `uid` `uid` int(11) unsigned NULL AFTER `ip`,
    CHANGE `time` `time` int(11) unsigned NOT NULL DEFAULT '0' AFTER `uid`,
    CHANGE `action` `action` int(11) unsigned
        NOT NULL DEFAULT '0' AFTER `time`,
    CHANGE `data` `data` varchar(50) COLLATE 'utf8mb4_unicode_ci'
        NOT NULL DEFAULT '' AFTER `action`,
    ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_members`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `name` `name` varchar(50)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `id`,
    CHANGE `pass` `pass` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `name`,
    CHANGE `email` `email` varchar(50)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `pass`,
    CHANGE `sig` `sig` text
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `email`,
    CHANGE `posts` `posts` int(11) unsigned NOT NULL DEFAULT '0' AFTER `sig`,
    CHANGE `group_id` `group_id` int(11) unsigned NULL AFTER `posts`,
    CHANGE `avatar` `avatar` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `group_id`,
    CHANGE `usertitle` `usertitle` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `avatar`,
    CHANGE `contact_skype` `contact_skype` varchar(50)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `usertitle`,
    CHANGE `contact_yim` `contact_yim` varchar(50)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `contact_skype`,
    CHANGE `contact_msn` `contact_msn` varchar(50)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `contact_yim`,
    CHANGE `contact_gtalk` `contact_gtalk` varchar(50)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `contact_msn`,
    CHANGE `contact_aim` `contact_aim` varchar(50)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `contact_gtalk`,
    CHANGE `website` `website` varchar(50)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `contact_aim`,
    CHANGE `about` `about` text COLLATE 'utf8mb4_unicode_ci'
        NOT NULL DEFAULT '' AFTER `website`,
    CHANGE `display_name` `display_name` varchar(30)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `about`,
    CHANGE `full_name` `full_name` varchar(50)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `display_name`,
    CHANGE `contact_steam` `contact_steam` varchar(50)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `full_name`,
    CHANGE `location` `location` varchar(100)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `contact_steam`,
    CHANGE `gender` `gender` enum('','male','female','other')
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `location`,
    CHANGE `friends` `friends` text
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `gender`,
    CHANGE `enemies` `enemies` text
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `friends`,
    CHANGE `sound_shout` `sound_shout` tinyint(1) unsigned
        NOT NULL DEFAULT '1' AFTER `enemies`,
    CHANGE `sound_im` `sound_im` tinyint(1) unsigned
        NOT NULL DEFAULT '1' AFTER `sound_shout`,
    CHANGE `sound_pm` `sound_pm` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `sound_im`,
    CHANGE `sound_postinmytopic` `sound_postinmytopic` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `sound_pm`,
    CHANGE `sound_postinsubscribedtopic` `sound_postinsubscribedtopic`
        tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `sound_postinmytopic`,
    CHANGE `notify_pm` `notify_pm` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `sound_postinsubscribedtopic`,
    CHANGE `notify_postinmytopic` `notify_postinmytopic` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `notify_pm`,
    CHANGE `notify_postinsubscribedtopic` `notify_postinsubscribedtopic`
        tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `notify_postinmytopic`,
    CHANGE `ucpnotepad` `ucpnotepad` text COLLATE 'utf8mb4_unicode_ci'
        NOT NULL DEFAULT '' AFTER `notify_postinsubscribedtopic`,
    CHANGE `skin_id` `skin_id` int(11) unsigned NULL AFTER `ucpnotepad`,
    CHANGE `contact_twitter` `contact_twitter` varchar(50)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `skin_id`,
    CHANGE `email_settings` `email_settings` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `contact_twitter`,
    CHANGE `nowordfilter` `nowordfilter` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `email_settings`,
    CHANGE `ip` `ip` varbinary(16) NOT NULL DEFAULT '' AFTER `nowordfilter`,
    CHANGE `mod` `mod` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `ip`,
    CHANGE `wysiwyg` `wysiwyg` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `mod`,
    COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_member_groups`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `title` `title` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `id`,
    CHANGE `can_post` `can_post` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `title`,
    CHANGE `can_edit_posts` `can_edit_posts` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_post`,
    CHANGE `can_post_topics` `can_post_topics` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_edit_posts`,
    CHANGE `can_edit_topics` `can_edit_topics` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_post_topics`,
    CHANGE `can_add_comments` `can_add_comments` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_edit_topics`,
    CHANGE `can_delete_comments` `can_delete_comments` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_add_comments`,
    CHANGE `can_view_board` `can_view_board` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_delete_comments`,
    CHANGE `can_view_offline_board` `can_view_offline_board`
        tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `can_view_board`,
    CHANGE `flood_control` `flood_control` int(11) unsigned
        NOT NULL DEFAULT '0' AFTER `can_view_offline_board`,
    CHANGE `can_override_locked_topics` `can_override_locked_topics`
        tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `flood_control`,
    CHANGE `icon` `icon` varchar(255) COLLATE 'utf8mb4_unicode_ci'
        NOT NULL DEFAULT '' AFTER `can_override_locked_topics`,
    CHANGE `can_shout` `can_shout` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `icon`,
    CHANGE `can_moderate` `can_moderate` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_shout`,
    CHANGE `can_delete_shouts` `can_delete_shouts` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_moderate`,
    CHANGE `can_delete_own_shouts` `can_delete_own_shouts`
        tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `can_delete_shouts`,
    CHANGE `can_karma` `can_karma` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_delete_own_shouts`,
    CHANGE `can_im` `can_im` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_karma`,
    CHANGE `can_pm` `can_pm` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_im`,
    CHANGE `can_lock_own_topics` `can_lock_own_topics` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_pm`,
    CHANGE `can_delete_own_topics` `can_delete_own_topics` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_lock_own_topics`,
    CHANGE `can_use_sigs` `can_use_sigs` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_delete_own_topics`,
    CHANGE `can_attach` `can_attach` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_use_sigs`,
    CHANGE `can_delete_own_posts` `can_delete_own_posts` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_attach`,
    CHANGE `can_poll` `can_poll` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_delete_own_posts`,
    CHANGE `can_access_acp` `can_access_acp` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_poll`,
    CHANGE `can_view_shoutbox` `can_view_shoutbox` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_access_acp`,
    CHANGE `can_view_stats` `can_view_stats` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_view_shoutbox`,
    CHANGE `legend` `legend` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `can_view_stats`,
    CHANGE `can_view_fullprofile` `can_view_fullprofile` tinyint(1) unsigned
        NOT NULL DEFAULT '1' AFTER `legend`,
    COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_messages`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `to` `to` int(11) unsigned NULL AFTER `id`,
    CHANGE `from` `from` int(11) unsigned NULL AFTER `to`,
    CHANGE `title` `title` varchar(255) COLLATE 'utf8mb4_unicode_ci'
        NOT NULL AFTER `from`,
    CHANGE `message` `message` text COLLATE 'utf8mb4_unicode_ci'
        NOT NULL AFTER `title`,
    CHANGE `read` `read` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `message`,
    CHANGE `del_recipient` `del_recipient` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `read`,
    CHANGE `del_sender` `del_sender` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `del_recipient`,
    CHANGE `flag` `flag` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `del_sender`,
    COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_pages`
    CHANGE `act` `act` varchar(25) COLLATE 'utf8mb4_unicode_ci' NOT NULL FIRST,
    CHANGE `page` `page` text
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `act`,
    COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_posts`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `auth_id` `auth_id` int(11) unsigned NULL AFTER `id`,
    CHANGE `post` `post` text COLLATE 'utf8mb4_unicode_ci'
        NOT NULL AFTER `auth_id`,
    CHANGE `showsig` `showsig` tinyint(1) unsigned
        NOT NULL DEFAULT '1' AFTER `post`,
    CHANGE `showemotes` `showemotes` tinyint(1) unsigned
        NOT NULL DEFAULT '1' AFTER `showsig`,
    CHANGE `tid` `tid` int(11) unsigned NOT NULL AFTER `showemotes`,
    CHANGE `newtopic` `newtopic` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `tid`,
    CHANGE `ip` `ip` varbinary(16) NOT NULL DEFAULT '' AFTER `newtopic`,
    CHANGE `editby` `editby` int(11) unsigned NULL AFTER `ip`,
    CHANGE `rating` `rating` text
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `editby`,
    ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_profile_comments`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `to` `to` int(11) unsigned NOT NULL AFTER `id`,
    CHANGE `from` `from` int(11) unsigned NOT NULL AFTER `to`,
    CHANGE `comment` `comment` text
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `from`,
    COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_ratingniblets`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `img` `img` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `id`,
    CHANGE `title` `title` varchar(50)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `img`,
    ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_reports`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `status` `status` tinyint(4) unsigned NOT NULL AFTER `reporter`,
    CHANGE `reason` `reason` text
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `status`,
    COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_session`
    CHANGE `id` `id` varchar(191) COLLATE 'utf8mb4_unicode_ci' NOT NULL FIRST,
    CHANGE `uid` `uid` int(11) unsigned NULL AFTER `id`,
    CHANGE `ip` `ip` varbinary(16) NOT NULL DEFAULT '' AFTER `uid`,
    CHANGE `vars` `vars` text
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `ip`,
    CHANGE `runonce` `runonce` text
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `last_action`,
    CHANGE `location` `location` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `runonce`,
    CHANGE `users_online_cache` `users_online_cache` text
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `location`,
    CHANGE `is_bot` `is_bot` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `users_online_cache`,
    CHANGE `buddy_list_cache` `buddy_list_cache` text
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `is_bot`,
    CHANGE `location_verbose` `location_verbose` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `buddy_list_cache`,
    CHANGE `useragent` `useragent` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `location_verbose`,
    CHANGE `forumsread` `forumsread` blob
        NOT NULL DEFAULT '' AFTER `useragent`,
    CHANGE `topicsread` `topicsread` blob
        NOT NULL DEFAULT '' AFTER `forumsread`,
    CHANGE `hide` `hide` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `topicsread`,
    ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_shouts`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `uid` `uid` int(11) unsigned NULL AFTER `id`,
    CHANGE `shout` `shout` varchar(511)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uid`,
    CHANGE `ip` `ip` varbinary(16) NOT NULL DEFAULT '' AFTER `shout`,
    COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_skins`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `using` `using` int(11) unsigned NOT NULL DEFAULT '0' AFTER `id`,
    CHANGE `title` `title` varchar(250)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `using`,
    CHANGE `custom` `custom` tinyint(1) unsigned
        NOT NULL DEFAULT '1' AFTER `title`,
    CHANGE `wrapper` `wrapper` text
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `custom`,
    CHANGE `default` `default` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `wrapper`,
    CHANGE `hidden` `hidden` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `default`,
    COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_stats`
    CHANGE `posts` `posts` int(11) unsigned NOT NULL DEFAULT '0' FIRST,
    CHANGE `topics` `topics` int(11) unsigned
        NOT NULL DEFAULT '0' AFTER `posts`,
    CHANGE `members` `members` int(11) unsigned
        NOT NULL DEFAULT '0' AFTER `topics`,
    CHANGE `most_members` `most_members` int(11) unsigned
        NOT NULL DEFAULT '0' AFTER `members`,
    CHANGE `most_members_day` `most_members_day` int(11) unsigned
        NOT NULL DEFAULT '0' AFTER `most_members`,
    CHANGE `last_register` `last_register` int(11) unsigned
        NULL AFTER `most_members_day`,
    COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_textrules`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `type` `type` enum('bbcode','emote','badword')
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `id`,
    CHANGE `needle` `needle` varchar(50)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `type`,
    CHANGE `replacement` `replacement` varchar(511)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `needle`,
    CHANGE `enabled` `enabled` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `replacement`,
    ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
        <<<'EOT'
ALTER TABLE `blueprint_topics`
    CHANGE `id` `id` int(11) unsigned NOT NULL AUTO_INCREMENT FIRST,
    CHANGE `title` `title` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NULL AFTER `id`,
    CHANGE `subtitle` `subtitle` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `title`,
    CHANGE `lp_uid` `lp_uid` int(11) unsigned NULL AFTER `subtitle`,
    CHANGE `fid` `fid` int(11) unsigned NULL AFTER `lp_uid`,
    CHANGE `auth_id` `auth_id` int(11) unsigned NULL AFTER `fid`,
    CHANGE `replies` `replies` int(11) unsigned
        NOT NULL DEFAULT '0' AFTER `auth_id`,
    CHANGE `views` `views` int(11) unsigned
        NOT NULL DEFAULT '0' AFTER `replies`,
    CHANGE `pinned` `pinned` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `views`,
    CHANGE `poll_choices` `poll_choices` mediumtext
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `pinned`,
    CHANGE `poll_results` `poll_results` mediumtext
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `poll_choices`,
    CHANGE `poll_q` `poll_q` varchar(255)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `poll_results`,
    CHANGE `poll_type` `poll_type` enum('','single','multi')
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `poll_q`,
    CHANGE `summary` `summary` varchar(50)
        COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' AFTER `poll_type`,
    CHANGE `locked` `locked` tinyint(1) unsigned
        NOT NULL DEFAULT '0' AFTER `summary`,
    CHANGE `op` `op` int(11) unsigned
        NULL AFTER `locked`,
    CHANGE `cal_event` `cal_event` int(11)
        unsigned NOT NULL DEFAULT '0' AFTER `op`,
    ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
EOT
        ,
    );
    $table = str_replace('`', "'", $DB->ftable('tokens'));
    $result = $DB->safequery("SHOW TABLES LIKE {$table};");
    if ($DB->num_rows($result) < 1) {
        $queries[] = <<<'EOT'
    CREATE TABLE `blueprint_tokens` (
      `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
      `type` enum('login','forgotpassword')
        COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'login',
      `uid` int(11) unsigned NOT NULL,
      `expires` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
      PRIMARY KEY (`token`),
      KEY `uid` (`uid`),
      KEY `expires` (`expires`),
      KEY `type` (`type`),
      CONSTRAINT `blueprint_tokens_ibfk_1`
        FOREIGN KEY (`uid`) REFERENCES `blueprint_members` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOT;
    }
    foreach ($queries as $query) {
        $query = str_replace('blueprint_', $board . '_', $query);
        $result = $DB->safequery($query);
        $DB->disposeresult($result);
    }

    // Check if we need to create indexes keys.
    $table = $DB->ftable('files');
    $result = $DB->safequery("SHOW CREATE TABLE {$table}");
    $createTableStatement = $DB->row($result);
    $createTableStatement = array_pop($createTableStatement);
    if (!preg_match("/KEY\s+`hash`/i", $createTableStatement)) {
        $result = $DB->safequery(
            <<<EOT
ALTER TABLE {$table}
    ADD INDEX `hash` (`hash`);
EOT
        );
        $DB->disposeresult($result);
    }

    // Fix dates.
    foreach ($dateFixes as $table => $columns) {
        $table = $DB->ftable($table);
        // Get table columns.
        $tableColumns = array();
        $result = $DB->safequery("DESCRIBE {$table}");
        while ($row = $DB->arow($result)) {
            if (isset($row['Field'])) {
                $tableColumns[] = mb_strtolower($row['Field']);
            }
        }
        foreach ($columns as $old => $info) {
            if (!in_array($old, $tableColumns)) {
                // Can't run if table doesn't exist.
                continue;
            }
            // Check if update is necessary.
            $result = $DB->safequery(
                <<<EOT
SELECT
    TIMESTAMPDIFF(DAY, DATE(MAX(`{$old}`)), DATE(MAX(`{$old}`))) as `date_check`
    FROM {$table}
    LIMIT 1;
EOT
            );
            $row = $DB->arow($result);
            $DB->disposeresult($result);
            if (null === $row['date_check']) {
                $new = $info['new'];
                $pos = $info['pos'];
                $queries = array(
                    <<<EOT
    UPDATE $table SET `$old` = 0 WHERE `$old` IS NULL;
EOT
                ,
                    <<<EOT
    ALTER TABLE $table
        CHANGE `$old` `{$old}_tmp` int(11) unsigned NOT NULL AFTER `$pos`,
        ADD `$new` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `{$old}_tmp`;
EOT
                ,
                    <<<EOT
    UPDATE $table SET `$new`=FROM_UNIXTIME(COALESCE(`{$old}_tmp`, 0));
EOT
                ,
                    <<<EOT
    ALTER TABLE $table DROP `{$old}_tmp`;
EOT
                ,
                );
                foreach ($queries as $query) {
                    $result = $DB->safequery($query);
                    $DB->disposeresult($result);
                }
            }
        }
    }

    // Birthday field.
    $table = $DB->ftable('members');
    // Get member columns.
    $columns = array();
    $result = $DB->safequery("DESCRIBE {$table}");
    while ($row = $DB->arow($result)) {
        if (isset($row['Field'])) {
            $columns[] = mb_strtolower($row['Field']);
        }
    }
    $DB->disposeresult($result);
    if (
        in_array('dob_year', $columns)
        && in_array('dob_month', $columns)
        && in_array('dob_day', $columns)
        && !in_array('birthdate', $columns)
    ) {
        $queries = array(
            <<<EOT
    ALTER TABLE $table
        ADD `birthdate` date NOT NULL DEFAULT '0000-00-00' AFTER `dob_year`;
EOT
        ,
            <<<EOT
UPDATE $table SET `dob_year` = 0 WHERE `dob_year` IS NULL;
EOT
        ,
            <<<EOT
UPDATE $table SET `dob_month` = 0 WHERE `dob_month` IS NULL;
EOT
        ,
            <<<EOT
UPDATE $table SET `dob_day` = 0 WHERE `dob_day` IS NULL;
EOT
        ,
            <<<EOT
    UPDATE $table SET `birthdate`=COALESCE(CONCAT(
        `dob_year`,
        '-',
        `dob_month`,
        '-',
        `dob_day`
    ),FROM_UNIXTIME(0));
EOT
        ,
            <<<EOT
    ALTER TABLE $table
        DROP `dob_day`,
        DROP `dob_month`,
        DROP `dob_year`;
EOT
        ,
        );
        foreach ($queries as $query) {
            $result = $DB->safequery($query);
            $DB->disposeresult($result);
        }
    }

    // Fix IP fields.
    foreach ($ipFixes as $table => $column) {
        $table = $DB->ftable($table);
        $result = $DB->safequery(
            <<<EOT
SELECT INET6_NTOA(MAX(`{$column}`)) as `ip_check`
    FROM {$table}
    LIMIT 1;
EOT
        );
        $row = $DB->arow($result);
        $DB->disposeResult($result);
        if (null === $row['ip_check']) {
            $result = $DB->safequery(
                <<<EOT
UPDATE {$table} SET `{$column}` = COALESCE(
    INET6_ATON(INET_NTOA(`{$column}`)),
    INET6_ATON(INET_NTOA(0))
);
EOT
            );
            $DB->disposeresult($result);
        }
    }

    // Run int to null.
    foreach ($intToNull as $table => $columns) {
        $table = $DB->ftable($table);
        foreach ($columns as $column) {
            $result = $DB->safequery(
                <<<EOT
UPDATE {$table}
SET `{$column}` = NULL
WHERE `{$column}` <= 0;
EOT
            );
            $DB->disposeresult($result);
        }
    }

    $DB->safequery('SET foreign_key_checks = 0;');

    // Fix foreign keys.
    foreach ($fixForeignKeyRelations as $table => $columns) {
        $table = $DB->ftable($table);
        $result = $DB->safequery(
            "SHOW CREATE TABLE {$table}"
        );
        $createTableStatement = $DB->row($result);
        $createTableStatement = array_pop($createTableStatement);
        $DB->disposeresult($result);
        foreach ($columns as $column => $foreign) {
            $foreign['table'] = $DB->ftable($foreign['table']);
            if ('delete' === $foreign['type']) {
                $result = $DB->safequery(
                    <<<EOT
DELETE FROM {$table}
WHERE `{$column}` NOT IN (
    SELECT `{$foreign['column']}`
    FROM {$foreign['table']}
) AND `{$column}` IS NOT NULL;
EOT
                );
                $DB->disposeresult($result);
            } elseif ('null' === $foreign['type']) {
                $result = $DB->safequery(
                    <<<EOT
UPDATE {$table}
SET `{$column}` = NULL
WHERE `{$column}` NOT IN (
    SELECT `{$foreign['column']}`
    FROM {$foreign['table']}
) AND `{$column}` IS NOT NULL;
EOT
                );
                $DB->disposeresult($result);
            }
            if (
                !preg_match(
                    "/FOREIGN\s+KEY\s+\(`{$column}`\)\s+REFERENCES\s+"
                    . "{$foreign['table']}\s+\(`{$foreign['column']}`\)/i",
                    $createTableStatement
                )
            ) {
                $result = $DB->safequery(
                    str_replace(
                        'blueprint_',
                        $board . '_',
                        $foreign['query']
                    )
                );
                $DB->disposeresult($result);
            }
        }
    }
}

// Create lock file.
$file = fopen(SERVICE_ROOT . '/update.lock', 'w');
fwrite($file, '');
fclose($file);
