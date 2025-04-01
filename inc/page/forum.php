<?php

declare(strict_types=1);

$PAGE->loadmeta('forum');

$IDX = new FORUM();
final class FORUM
{
    public $topicsRead = [];

    public $forumsRead = [];

    public $forumReadTime = 0;

    public $numperpage = 20;

    /**
     * @var float|int
     */
    public $page = 0;

    public function __construct()
    {
        global $JAX,$PAGE;
        if (
            isset($JAX->b['page'])
            && is_numeric($JAX->b['page'])
            && $JAX->b['page'] > 0
        ) {
            $this->page = $JAX->b['page'] - 1;
        }

        preg_match('@^([a-zA-Z_]+)(\d+)$@', (string) $JAX->g['act'], $act);
        if (isset($JAX->b['markread']) && $JAX->b['markread']) {
            $this->markread($act[2]);
            $PAGE->location('?');

            return;
        }

        if ($PAGE->jsupdate) {
            $this->update();
        } elseif (
            isset($JAX->b['replies'])
            && is_numeric($JAX->b['replies'])
        ) {
            $this->getreplysummary($JAX->b['replies']);
        } else {
            $this->viewforum($act[2]);
        }
    }

    public function viewforum($fid)
    {
        global $DB,$PAGE,$JAX,$PERMS,$USER;

        // If no fid supplied, go to the index and halt execution.
        if (!$fid) {
            return $PAGE->location('?');
        }

        $page = '';
        $rows = '';
        $table = '';
        $unread = false;

        $result = $DB->safespecial(
            <<<'EOT'
                SELECT f.`id` AS `id`,f.`cat_id` AS `cat_id`,f.`title` AS `title`,
                    f.`subtitle` AS `subtitle`,f.`lp_uid` AS `lp_uid`,
                    UNIX_TIMESTAMP(f.`lp_date`) AS `lp_date`,f.`lp_tid` AS `lp_tid`,
                    f.`lp_topic` AS `lp_topic`,f.`path` AS `path`,f.`show_sub` AS `show_sub`,
                    f.`redirect` AS `redirect`,f.`topics` AS `topics`,f.`posts` AS `posts`,
                    f.`order` AS `order`,f.`perms` AS `perms`,f.`orderby` AS `orderby`,
                    f.`nocount` AS `nocount`,f.`redirects` AS `redirects`,
                    f.`trashcan` AS `trashcan`,f.`mods` AS `mods`,f.`show_ledby` AS `show_ledby`,
                    c.`title` AS `cat`
                FROM %t f
                LEFT JOIN %t c
                    ON f.`cat_id`=c.`id`
                WHERE f.`id`=? LIMIT 1
                EOT
            ,
            ['forums', 'categories'],
            $fid,
        );
        $fdata = $DB->arow($result);
        $DB->disposeresult($result);

        if (!$fdata) {
            $PAGE->JS('alert', $DB->error());

            return $PAGE->location('?');
        }

        if ($fdata['redirect']) {
            $PAGE->JS('softurl');
            $DB->safespecial(
                <<<'EOT'
                    UPDATE %t
                    SET `redirects` = `redirects` + 1
                    WHERE `id`=?
                    EOT
                ,
                ['forums'],
                $DB->basicvalue($fid),
            );

            return $PAGE->location($fdata['redirect']);
        }

        $title = &$fdata['title'];

        $fdata['perms'] = $JAX->parseperms(
            $fdata['perms'],
            $USER ? $USER['group_id'] : 3,
        );
        if (!$fdata['perms']['read']) {
            $PAGE->JS('alert', 'no permission');

            return $PAGE->location('?');
        }

        // NOW we can actually start building the page
        // subforums
        // right now, this loop also fixes the number of pages to show in a forum
        // parent forum - subforum topics = total topics
        // I'm fairly sure this is faster than doing
        // `SELECT count(*) FROM topics`... but I haven't benchmarked it.
        $result = $DB->safespecial(
            <<<'EOT'
                SELECT f.`id` AS `id`,f.`cat_id` AS `cat_id`,f.`title` AS `title`,
                    f.`subtitle` AS `subtitle`,f.`lp_uid` AS `lp_uid`,
                    UNIX_TIMESTAMP(f.`lp_date`) AS `lp_date`,f.`lp_tid` AS `lp_tid`,
                    f.`lp_topic` AS `lp_topic`,f.`path` AS `path`,f.`show_sub` AS `show_sub`,
                    f.`redirect` AS `redirect`,f.`topics` AS `topics`,f.`posts` AS `posts`,
                    f.`order` AS `order`,f.`perms` AS `perms`,f.`orderby` AS `orderby`,
                    f.`nocount` AS `nocount`,f.`redirects` AS `redirects`,
                    f.`trashcan` AS `trashcan`,f.`mods` AS `mods`,f.`show_ledby` AS `show_ledby`,
                    m.`display_name` AS `lp_name`,m.`group_id` AS `lp_gid`
                FROM %t f
                LEFT JOIN %t m
                ON f.`lp_uid`=m.`id`
                WHERE f.`path`=?
                    OR f.`path` LIKE ?
                ORDER BY f.`order`
                EOT
            ,
            ['forums', 'members'],
            $fid,
            "% {$fid}",
        );
        $rows = '';
        while ($f = $DB->arow($result)) {
            $fdata['topics'] -= $f['topics'];
            if ($this->page) {
                continue;
            }

            $rows .= $PAGE->meta(
                'forum-subforum-row',
                $f['id'],
                $f['title'],
                $f['subtitle'],
                $PAGE->meta(
                    'forum-subforum-lastpost',
                    $f['lp_tid'],
                    $JAX->pick($f['lp_topic'], '- - - - -'),
                    $f['lp_name'] ? $PAGE->meta(
                        'user-link',
                        $f['lp_uid'],
                        $f['lp_gid'],
                        $f['lp_name'],
                    ) : 'None',
                    $JAX->pick($JAX->date($f['lp_date']), '- - - - -'),
                ),
                $f['topics'],
                $f['posts'],
                ($read = $this->isForumRead($f)) ? 'read' : 'unread',
                $read ? $JAX->pick(
                    $PAGE->meta(
                        'subforum-icon-read',
                    ),
                    $PAGE->meta(
                        'icon-read',
                    ),
                ) : $JAX->pick(
                    $PAGE->meta('subforum-icon-unread'),
                    $PAGE->meta('icon-unread'),
                ),
            );
            if ($read) {
                continue;
            }

            $unread = true;
        }

        if ($rows !== '' && $rows !== '0') {
            $page .= $PAGE->collapsebox(
                'Subforums',
                $PAGE->meta('forum-subforum-table', $rows),
            );
        }

        $rows = '';
        $table = '';

        // Generate pages.
        $numpages = ceil($fdata['topics'] / $this->numperpage);
        $forumpages = '';
        if ($numpages !== 0.0) {
            foreach ($JAX->pages($numpages, $this->page + 1, 10) as $v) {
                $forumpages .= '<a href="?act=vf' . $fid . '&amp;page='
                    . $v . '"' . ($v - 1 === $this->page ? ' class="active"' : '')
                    . '>' . $v . '</a> · ';
            }
        }

        // Buttons.
        $forumbuttons = '&nbsp;'
            . ($fdata['perms']['start'] ? '<a href="?act=post&amp;fid=' . $fid . '">'
            . $PAGE->meta(
                $PAGE->metaexists('button-newtopic')
                ? 'button-newtopic' : 'forum-button-newtopic',
            ) . '</a>' : '');
        $page .= $PAGE->meta(
            'forum-pages-top',
            $forumpages,
        ) . $PAGE->meta(
            'forum-buttons-top',
            $forumbuttons,
        );

        // Do order by.
        $orderby = '`lp_date` DESC';
        if ($fdata['orderby']) {
            $fdata['orderby'] = (int) $fdata['orderby'];
            if (($fdata['orderby'] & 1) !== 0) {
                $orderby = 'ASC';
                --$fdata['orderby'];
            } else {
                $orderby = 'DESC';
            }

            if ($fdata['orderby'] === 2) {
                $orderby = '`id` ' . $orderby;
            } elseif ($fdata['orderby'] === 4) {
                $orderby = '`title` ' . $orderby;
            } else {
                $orderby = '`lp_date` ' . $orderby;
            }
        }

        // Topics.
        $result = $DB->safespecial(
            <<<EOT
                SELECT t.`id` AS `id`,t.`title` AS `title`,t.`subtitle` AS `subtitle`,
                    t.`lp_uid` AS `lp_uid`,UNIX_TIMESTAMP(t.`lp_date`) AS `lp_date`,
                    t.`fid` AS `fid`,t.`auth_id` AS `auth_id`,t.`replies` AS `replies`,
                    t.`views` AS `views`,t.`pinned` AS `pinned`,
                    t.`poll_choices` AS `poll_choices`,t.`poll_results` AS `poll_results`,
                    t.`poll_q` AS `poll_q`,t.`poll_type` AS `poll_type`,
                    t.`summary` AS `summary`,t.`locked` AS `locked`,
                    UNIX_TIMESTAMP(t.`date`) AS `date`,t.`op` AS `op`,
                    t.`cal_event` AS `cal_event`,
                    m.`display_name` AS `lp_name`,m.`group_id` AS `lp_gid`,
                    m2.`group_id` AS `auth_gid`,m2.`display_name` AS `auth_name`
                FROM (
                    SELECT `id`,`title`,`subtitle`,`lp_uid`,`lp_date`,`fid`,`auth_id`,`replies`,`views`,
                    `pinned`,`poll_choices`,`poll_results`,`poll_q`,`poll_type`,`summary`,
                    `locked`,UNIX_TIMESTAMP(`date`) AS `date`,`op`,`cal_event`
                    FROM %t
                    WHERE `fid`=?
                    ORDER BY `pinned` DESC,{$orderby}
                    LIMIT ?,?
                ) t
                LEFT JOIN %t m
                    ON t.`lp_uid` = m.`id`
                LEFT JOIN %t m2
                ON t.`auth_id` = m2.`id`
                EOT
            ,
            ['topics', 'members', 'members'],
            $fid,
            $this->page * $this->numperpage,
            $this->numperpage,
        );

        while ($f = $DB->arow($result)) {
            $pages = '';
            if ($f['replies'] > 9) {
                foreach ($JAX->pages(ceil(($f['replies'] + 1) / 10), 1, 10) as $v) {
                    $pages .= "<a href='?act=vt" . $f['id']
                        . "&amp;page={$v}'>{$v}</a> ";
                }

                $pages = $PAGE->meta('forum-topic-pages', $pages);
            }

            $read = false;
            $unread = false;
            $rows .= $PAGE->meta(
                'forum-row',
                $f['id'],
                // 1
                $JAX->wordfilter($f['title']),
                // 2
                $JAX->wordfilter($f['subtitle']),
                // 3
                $PAGE->meta('user-link', $f['auth_id'], $f['auth_gid'], $f['auth_name']),
                // 4
                $f['replies'],
                // 5
                number_format($f['views']),
                // 6
                $JAX->date($f['lp_date']),
                // 7
                $PAGE->meta('user-link', $f['lp_uid'], $f['lp_gid'], $f['lp_name']),
                // 8
                ($f['pinned'] ? 'pinned' : '') . ' ' . ($f['locked'] ? 'locked' : ''),
                // 9
                $f['summary'] ? $f['summary'] . (mb_strlen((string) $f['summary']) > 45 ? '...' : '') : '',
                // 10
                $PERMS['can_moderate'] ? '<a href="?act=modcontrols&do=modt&tid='
                . $f['id'] . '" class="moderate" onclick="RUN.modcontrols.togbutton(this)"></a>' : '',
                // 11
                $pages,
                // 12
                ($read = $this->isTopicRead($f, $fid)) ? 'read' : 'unread',
                // 13
                $read ? $JAX->pick(
                    $PAGE->meta('topic-icon-read'),
                    $PAGE->meta('icon-read'),
                )
                : $JAX->pick(
                    $PAGE->meta('topic-icon-unread'),
                    $PAGE->meta('icon-read'),
                ),
                // 14
            );
            if ($read) {
                continue;
            }

            $unread = true;
        }

        // If they're on the first page and no topics
        // were marked as unread, mark the whole forum as read
        // since we don't care about pages past the first one.
        if (!$this->page && !$unread) {
            $this->markread($fid);
        }

        if ($rows !== '' && $rows !== '0') {
            $table = $PAGE->meta('forum-table', $rows);
        } else {
            if ($this->page > 0) {
                return $PAGE->location('?act=vf' . $fid);
            }

            if ($fdata['perms']['start']) {
                $table = $PAGE->error(
                    "This forum is empty! Don't like it? "
                    . "<a href='?act=post&amp;fid=" . $fid . "'>Create a topic!</a>",
                );
            }
        }

        $page .= $PAGE->meta('box', ' id="fid_' . $fid . '_listing"', $title, $table);
        $page .= $PAGE->meta('forum-pages-bottom', $forumpages);
        $page .= $PAGE->meta('forum-buttons-bottom', $forumbuttons);

        // Start building the nav path.
        $path[$fdata['cat']] = '?act=vc' . $fdata['cat_id'];
        if ($fdata['path']) {
            $pathids = explode(' ', (string) $fdata['path']);
            $forums = [];
            $result = $DB->safeselect(
                ['id', 'title'],
                'forums',
                'WHERE `id` IN ?',
                $pathids,
            );
            while ($f = $DB->arow($result)) {
                $forums[$f['id']] = [$f['title'], '?act=vf' . $f['id']];
            }

            foreach ($pathids as $v) {
                $path[$forums[$v][0]] = $forums[$v][1];
            }
        }

        $path[$title] = "?act=vf{$fid}";
        $PAGE->updatepath($path);
        if ($PAGE->jsaccess) {
            $PAGE->JS('update', 'page', $page);
        } else {
            $PAGE->append('PAGE', $page);
        }

        return null;
    }

    public function getreplysummary($tid): void
    {
        global $PAGE,$DB;
        $result = $DB->safespecial(
            <<<'EOT'
                SELECT m.`display_name` AS `name`,COUNT(p.`id`) AS `replies`
                FROM %t p
                LEFT JOIN %t m
                    ON p.`auth_id`=m.`id`
                WHERE `tid`=?
                GROUP BY p.`auth_id`
                ORDER BY `replies` DESC
                EOT
            ,
            ['posts', 'members'],
            $tid,
        );
        $page = '';
        while ($f = $DB->arow($result)) {
            $page .= '<tr><td>' . $f['name'] . '</td><td>' . $f['replies'] . '</td></tr>';
        }

        $PAGE->JS('softurl');
        $PAGE->JS(
            'window',
            [
                'content' => '<table>' . $page . '</table>',
                'title' => 'Post Summary',
            ],
        );
    }

    public function update(): void
    {
        // Update the topic listing.
    }

    public function isTopicRead($topic, $fid): bool
    {
        global $SESS,$USER,$JAX,$PAGE;
        if (empty($this->topicsRead)) {
            $this->topicsRead = $JAX->parsereadmarkers($SESS->topicsread);
        }

        if (empty($this->forumsRead)) {
            $fr = $JAX->parsereadmarkers($SESS->forumsread);
            if (isset($fr[$fid])) {
                $this->forumReadTime = $fr[$fid];
            }
        }

        if (!isset($this->topicsRead[$topic['id']])) {
            $this->topicsRead[$topic['id']] = 0;
        }

        return $topic['lp_date'] <= $JAX->pick(
            max($this->topicsRead[$topic['id']], $this->forumReadTime),
            $SESS->read_date,
            $USER && $USER['last_visit'],
        );
    }

    public function isForumRead($forum): bool
    {
        global $SESS,$USER,$JAX;
        if (!$this->forumsRead) {
            $this->forumsRead = $JAX->parsereadmarkers($SESS->forumsread);
        }

        return $forum['lp_date'] <= $JAX->pick(
            $this->forumsRead[$forum['id']] ?? null,
            $SESS->read_date,
            $USER && $USER['last_visit'],
        );
    }

    public function markread($id): void
    {
        global $SESS,$JAX;
        $forumsread = $JAX->parsereadmarkers($SESS->forumsread);
        $forumsread[$id] = time();
        $SESS->forumsread = json_encode($forumsread);
    }
}
