<?php

$PAGE->loadmeta('idx');
new IDX();
class IDX
{
    public $forumsread = array();

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
        $result = $DB->fetchResource('forums');
        $data = $this->subforums = $this->subforumids = $this->mods = array();

        // This while loop just grabs all of the data, displaying is done below.
        foreach ($result as $r) {
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
        $catq = $DB->fetchResource('categories');
        foreach ($catq as $r) {
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
        if (!$this->subforumids[$id]) {
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
            $result = $DB->fetchResource('members', [
                'ids' => $this->mods
            ]);
            foreach ($result as $row) {
                $this->moderatorinfo[$f['id']] = $PAGE->meta(
                    'user-link',
                    $row['id'],
                    $row['group_id'],
                    $row['display_name']
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
        $read = false;
        foreach ($a as $v) {
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
                    ($read = $this->isForumRead($v)) ? 'read' : 'unread',
                    <<<EOT
 <a id="fid_${vId}_icon"${hrefCode}>
    ${linkText}
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
        $result = $DB->fetchResource('stats');
        $nuserstoday = 0;
        $today = date('n j');
        foreach ($result as $f) {
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
<a href="?act=vu${fId}" class="user${fId} mgroup${fGroupId}${birthdayCode}"
    title="Last online: ${lastOnlineCode}" data-use-tooltip="true">
    ${fName}
</a>
EOT;
            $userstoday .= ', ';
            ++$nuserstoday;
        }
        $userstoday = mb_substr($userstoday, 0, -2);
        $usersonline = $this->getusersonlinelist();

        $result = $DB->fetchResource('member_groups', [
            'legend' => 1
        ]);
        foreach ($result as $row) {
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
            if ($f['uid'] || (isset($f['is_bot']) && $f['is_bot'])) {
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
                if ($f['last_action'] >= $SESS->last_update
                    || 'idle' == $f['status']
                    && $f['last_action'] > $lastActionIdle
                ) {
                    $list[] = array(
                        $f['uid'],
                        $f['group_id'],
                        ('active' != $f['status'] ?
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
        $result = $DB->fetchResource('forums', [
            'lp_date' => date('Y-m-d H:i:s', $JAX->pick($SESS->last_update, time()))
        ]);

        foreach ($result as $f) {
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
        if ($forum['lp_date'] > $JAX->pick(
            $this->forumsread[$forum['id']],
            $SESS->read_date,
            $USER['last_visit']
        )
        ) {
            return false;
        }

        return true;
    }
}
