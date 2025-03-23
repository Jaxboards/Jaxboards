<?php

$PAGE->loadmeta('idx');
new IDX();
class IDX
{
    public $forumsread = array();
    public $mods;
    public $subforumids;
    public $subforums;

    public function __construct()
    {
        global $PAGE,$CFG,$JAX,$SESS;
        if (isset($JAX->b['markread']) && $JAX->b['markread']) {
            $PAGE->JS('softurl');
            $SESS->forumsread = $SESS->topicsread = '';
            $SESS->read_date = time();
        }
        if ($PAGE->jsupdate) {
            $this->update();
        } else {
            $this->viewidx();
        }
    }

    public function viewidx()
    {
        global $DB,$PAGE,$SESS,$JAX,$USER,$CFG;
        $SESS->location_verbose = 'Viewing board index';
        $page = '';
        $result = $DB->safespecial(
            <<<'EOT'
SELECT f.`id` AS `id`,
    f.`cat_id` AS `cat_id`,f.`title` AS `title`,f.`subtitle` AS `subtitle`,
    f.`lp_uid` AS `lp_uid`,UNIX_TIMESTAMP(f.`lp_date`) AS `lp_date`,
    f.`lp_tid` AS `lp_tid`,f.`lp_topic` AS `lp_topic`,f.`path` AS `path`,
    f.`show_sub` AS `show_sub`,f.`redirect` AS `redirect`,
    f.`topics` AS `topics`,f.`posts` AS `posts`,f.`order` AS `order`,
    f.`perms` AS `perms`,f.`orderby` AS `orderby`,f.`nocount` AS `nocount`,
    f.`redirects` AS `redirects`,f.`trashcan` AS `trashcan`,f.`mods` AS `mods`,
    f.`show_ledby` AS `show_ledby`,m.`display_name` AS `lp_name`,
    m.`group_id` AS `lp_gid`
FROM %t f
LEFT JOIN %t m
    ON f.`lp_uid`=m.`id`
ORDER BY f.`order`, f.`title` ASC
EOT
            ,
            array(
                'forums',
                'members',
            )
        );
        $data = $this->subforums = $this->subforumids = $this->mods = array();

        // This while loop just grabs all of the data, displaying is done below.
        while ($r = $DB->arow($result)) {
            $perms = $JAX->parseperms($r['perms'], $USER ? $USER['group_id'] : 3);
            if ($r['perms'] && !$perms['view']) {
                continue;
            }
            // Store subforum details for later.
            if ($r['path']) {
                preg_match('@\\d+$@', $r['path'], $m);
                if (isset($this->subforums[$m[0]])) {
                    $this->subforumids[$m[0]][] = $r['id'];
                    $this->subforums[$m[0]] .= $PAGE->meta(
                        'idx-subforum-link',
                        $r['id'],
                        $r['title'],
                        $JAX->blockhtml($r['subtitle'])
                    ) . $PAGE->meta('idx-subforum-splitter');
                }
            } else {
                $data[$r['cat_id']][] = $r;
            }

            // Store mod details for later.
            if ($r['show_ledby'] && $r['mods']) {
                foreach (explode(',', $r['mods']) as $v) {
                    if ($v) {
                        $this->mods[$v] = 1;
                    }
                }
            }
        }
        $this->mods = array_keys($this->mods);
        $catq = $DB->safeselect(
            '`id`,`title`,`order`',
            'categories',
            'ORDER BY `order`,`title` ASC'
        );
        while ($r = $DB->arow($catq)) {
            if (!empty($data[$r['id']])) {
                $page .= $PAGE->collapsebox(
                    $r['title'],
                    $this->buildTable(
                        $data[$r['id']]
                    ),
                    'cat_' . $r['id']
                );
            }
        }
        $page .= $PAGE->meta('idx-tools');

        $page .= $this->getBoardStats();

        if ($PAGE->jsnewlocation) {
            $PAGE->JS('update', 'page', $page);
            $PAGE->updatepath();
        } else {
            $PAGE->append('PAGE', $page);
        }
    }

    public function getsubs($id)
    {
        if (!isset($this->subforumids[$id]) || !$this->subforumids[$id]) {
            return array();
        }
        $r = $this->subforumids[$id];
        foreach ($r as $v) {
            if ($this->subforumids[$v]) {
                $r = array_merge($r, $this->getsubs($v));
            }
        }

        return $r;
    }

    public function getmods($modids)
    {
        global $DB,$PAGE;
        if (!$this->moderatorinfo) {
            $this->moderatorinfo = array();
            $result = $DB->safeselect(
                '`id`,`display_name`,`group_id`',
                '`members`',
                'WHERE `id` IN ?',
                $this->mods
            );
            while ($f = $DB->arow($result)) {
                $this->moderatorinfo[$f['id']] = $PAGE->meta(
                    'user-link',
                    $f['id'],
                    $f['group_id'],
                    $f['display_name']
                );
            }
        }
        foreach (explode(',', $modids) as $v) {
            $r .= $this->moderatorinfo[$v] . $PAGE->meta('idx-ledby-splitter');
        }

        return mb_substr($r, 0, -mb_strlen($PAGE->meta('idx-ledby-splitter')));
    }

    public function buildTable($a)
    {
        global $PAGE,$JAX;
        if (!$a) {
            return;
        }
        $r = '';
        foreach ($a as $v) {
            $read = $this->isForumRead($v);
            $sf = '';
            if ($v['show_sub'] >= 1 && isset($this->subforums[$v['id']])) {
                $sf = $this->subforums[$v['id']];
            }
            if (2 == $v['show_sub']) {
                foreach ($this->getsubs($v['id']) as $i) {
                    $sf .= $this->subforums[$i];
                }
            }
            if ($v['redirect']) {
                $r .= $PAGE->meta(
                    'idx-redirect-row',
                    $v['id'],
                    $v['title'],
                    nl2br($v['subtitle']),
                    'Redirects: ' . $v['redirects'],
                    $JAX->pick(
                        $PAGE->meta('icon-redirect'),
                        $PAGE->meta('idx-icon-redirect')
                    )
                );
            } else {
                $vId = $v['id'];
                $hrefCode = !$read ?
                    ' href="?act=vf' . $v['id'] . '&amp;markread=1"' :
                    '';
                $linkText = $read ?
                    $JAX->pick(
                        $PAGE->meta('icon-read'),
                        $PAGE->meta('idx-icon-read')
                    ) : $JAX->pick(
                        $PAGE->meta('icon-unread'),
                        $PAGE->meta('idx-icon-unread')
                    );
                $r .= $PAGE->meta(
                    'idx-row',
                    $v['id'],
                    $JAX->wordfilter($v['title']),
                    nl2br($v['subtitle']),
                    $sf ?
                    $PAGE->meta(
                        'idx-subforum-wrapper',
                        mb_substr(
                            $sf,
                            0,
                            -1 * mb_strlen(
                                $PAGE->meta('idx-subforum-splitter')
                            )
                        )
                    ) : '',
                    $this->formatlastpost($v),
                    $PAGE->meta('idx-topics-count', $v['topics']),
                    $PAGE->meta('idx-replies-count', $v['posts']),
                    $read ? 'read' : 'unread',
                    <<<EOT
 <a id="fid_{$vId}_icon"{$hrefCode}>
    {$linkText}
</a>
EOT
                    ,
                    $v['show_ledby'] && $v['mods'] ?
                        $PAGE->meta(
                            'idx-ledby-wrapper',
                            $this->getmods($v['mods'])
                        ) : ''
                );
            }
        }

        return $PAGE->meta('idx-table', $r);
    }

    public function update()
    {
        $this->updateStats();
        $this->updateLastPosts();
    }

    public function getBoardStats()
    {
        global $DB,$JAX,$PAGE,$PERMS;
        if (!$PERMS['can_view_stats']) {
            return '';
        }
        $e = '';
        $legend = '';
        $page = '';
        $userstoday = '';
        $result = $DB->safespecial(
            <<<'EOT'
SELECT s.`posts` AS `posts`,s.`topics` AS `topics`,s.`members` AS `members`,
    s.`most_members` AS `most_members`,
    s.`most_members_day` AS `most_members_day`,
    s.`last_register` AS `last_register`,m.`group_id` AS `group_id`,
    m.`display_name` AS `display_name`
FROM %t s
LEFT JOIN %t m
ON s.`last_register`=m.`id`
EOT
            ,
            array('stats', 'members')
        );
        $stats = $DB->arow($result);
        $DB->disposeresult($result);

        $result = $DB->safespecial(
            <<<'EOT'
SELECT UNIX_TIMESTAMP(MAX(s.`last_update`)) AS `last_update`,m.`id` AS `id`,
    m.`group_id` AS `group_id`,m.`display_name` AS `name`,
    CONCAT(MONTH(m.`birthdate`),' ',DAY(m.`birthdate`)) AS `birthday`,
    UNIX_TIMESTAMP(MAX(s.`read_date`)) AS `read_date`,s.`hide` AS `hide`
FROM %t s
LEFT JOIN %t m
    ON s.`uid`=m.`id`
WHERE s.`uid` AND s.`hide` = 0
GROUP BY m.`id`
ORDER BY `name`
EOT
            ,
            array('session', 'members')
        );
        $nuserstoday = 0;
        $today = date('n j');
        while ($f = $DB->arow($result)) {
            if (!$f['id']) {
                continue;
            }
            $fId = $f['id'];
            $fName = $f['name'];
            $fGroupId = $f['group_id'];
            $birthdayCode = ($f['birthday'] == $today
                && ($CFG['birthdays'] & 1)) ? ' birthday' : '';
            $lastOnlineCode = $JAX->date(
                $f['hide'] ? $f['read_date'] : $f['last_update'],
                false
            );
            $userstoday .=
                <<<EOT
<a href="?act=vu{$fId}" class="user{$fId} mgroup{$fGroupId}{$birthdayCode}"
    title="Last online: {$lastOnlineCode}" data-use-tooltip="true">{$fName}</a>
EOT;
            $userstoday .= ', ';
            ++$nuserstoday;
        }
        $userstoday = mb_substr($userstoday, 0, -2);
        $usersonline = $this->getusersonlinelist();
        $result = $DB->safeselect(
            '`id`,`title`',
            'member_groups',
            'WHERE `legend`=1 ORDER BY `title`'
        );
        while ($row = $DB->arow($result)) {
            $legend .= '<a href="?" class="mgroup' . $row['id'] . '">' .
                $row['title'] . '</a> ';
        }
        $page .= $PAGE->meta(
            'idx-stats',
            $usersonline[1],
            $usersonline[0],
            $usersonline[2],
            $nuserstoday,
            $userstoday,
            number_format($stats['members']),
            number_format($stats['topics']),
            number_format($stats['posts']),
            $PAGE->meta(
                'user-link',
                $stats['last_register'],
                $stats['group_id'],
                $stats['display_name']
            ),
            $legend
        );

        return $page;
    }

    public function getusersonlinelist()
    {
        global $DB,$PAGE,$JAX,$CFG;
        $r = '';
        $guests = 0;
        $nummembers = 0;
        foreach ($DB->getUsersOnline() as $f) {
            if (!empty($f['uid']) || (isset($f['is_bot']) && $f['is_bot'])) {
                $title = $JAX->blockhtml(
                    $JAX->pick($f['location_verbose'], 'Viewing the board.')
                );
                if (isset($f['is_bot']) && $f['is_bot']) {
                    $r .= '<a class="user' . $f['uid'] . '" ' .
                        'title="' . $title . '" data-use-tooltip="true">' .
                        $f['name'] . '</a>';
                } else {
                    ++$nummembers;
                    $r .= sprintf(
                        '<a href="?act=vu%1$s" class="user%1$s mgroup%2$s" ' .
                            'title="%4$s" data-use-tooltip="true">' .
                            '%3$s</a>',
                        $f['uid'],
                        $f['group_id'] . ('idle' == $f['status'] ?
                            ' idle' :
                            ($f['birthday'] && ($CFG['birthdays'] & 1) ?
                            ' birthday' : '')),
                        $f['name'],
                        $title
                    );
                }
            } else {
                $guests = $f;
            }
        }

        return array($r, $nummembers, $guests);
    }

    public function updateStats()
    {
        global $PAGE,$DB,$SESS,$CFG;
        $list = array();
        if ($SESS->users_online_cache) {
            $oldcache = array_flip(explode(',', $SESS->users_online_cache));
        }
        $useronlinecache = '';
        foreach ($DB->getUsersOnline() as $f) {
            $lastActionIdle = $SESS->last_update - $CFG['timetoidle'] - 30;
            if ($f['uid'] || $f['is_bot']) {
                if (
                    $f['last_action'] >= $SESS->last_update
                    || 'idle' == $f['status']
                    && $f['last_action'] > $lastActionIdle
                ) {
                    $list[] = array(
                        $f['uid'],
                        $f['group_id'],
                        (
                            'active' != $f['status'] ?
                            $f['status'] :
                            ($f['birthday'] && ($CFG['birthdays'] & 1) ?
                            ' birthday' : '')
                        ),
                        $f['name'],
                        $f['location_verbose'],
                    );
                }
                if (isset($oldcache)) {
                    unset($oldcache[$f['uid']]);
                }
                $useronlinecache .= $f['uid'] . ',';
            }
        }
        if (isset($oldcache) && !empty($oldcache)) {
            $PAGE->JS('setoffline', implode(',', array_flip($oldcache)));
        }
        $SESS->users_online_cache = mb_substr($useronlinecache, 0, -1);
        if (!empty($list)) {
            $PAGE->JS('onlinelist', $list);
        }
    }

    public function updateLastPosts()
    {
        global $DB,$SESS,$PAGE,$JAX;
        $result = $DB->safespecial(
            <<<'EOT'
SELECT f.`id` AS `id`,f.`lp_tid` AS `lp_tid`,f.`lp_topic` AS `lp_topic`,
    UNIX_TIMESTAMP(f.`lp_date`) AS `lp_date`,f.`lp_uid` AS `lp_uid`,
    f.`topics` AS `topics`,f.`posts` AS `posts`,m.`display_name` AS `lp_name`,
    m.`group_id` AS `lp_gid`
FROM %t f
LEFT JOIN %t m
    ON f.`lp_uid`=m.`id`
WHERE f.`lp_date`>=?
EOT
            ,
            array('forums', 'members'),
            date('Y-m-d H:i:s', $JAX->pick($SESS->last_update, time()))
        );

        while ($f = $DB->arow($result)) {
            $PAGE->JS('addclass', '#fid_' . $f['id'], 'unread');
            $PAGE->JS(
                'update',
                '#fid_' . $f['id'] . '_icon',
                $JAX->pick(
                    $PAGE->meta('icon-unread'),
                    $PAGE->meta('idx-icon-unread')
                )
            );
            $PAGE->JS(
                'update',
                '#fid_' . $f['id'] . '_lastpost',
                $this->formatlastpost($f),
                '1'
            );
            $PAGE->JS(
                'update',
                '#fid_' . $f['id'] . '_topics',
                $PAGE->meta('idx-topics-count', $f['topics'])
            );
            $PAGE->JS(
                'update',
                '#fid_' . $f['id'] . '_replies',
                $PAGE->meta('idx-replies-count', $f['posts'])
            );
        }
    }

    public function formatlastpost($v)
    {
        global $PAGE,$JAX;

        return $PAGE->meta(
            'idx-row-lastpost',
            $v['lp_tid'],
            $JAX->pick(
                $JAX->wordfilter($v['lp_topic']),
                '- - - - -'
            ),
            $v['lp_uid'] ? $PAGE->meta(
                'user-link',
                $v['lp_uid'],
                $v['lp_gid'],
                $v['lp_name']
            ) : 'None',
            $JAX->pick($JAX->date($v['lp_date']), '- - - - -')
        );
    }

    public function isForumRead($forum)
    {
        global $SESS,$USER,$JAX;
        if (!$this->forumsread) {
            $this->forumsread = $JAX->parsereadmarkers($SESS->forumsread);
        }
        if (!isset($this->forumsread[$forum['id']])) {
            $this->forumsread[$forum['id']] = null;
        }
        return $forum['lp_date'] < max(
            $this->forumsread[$forum['id']],
            $SESS->read_date,
            $USER && $USER['last_visit']
        );
    }
}
