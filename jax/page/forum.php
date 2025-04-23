<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\Jax;
use Jax\Page;
use Jax\Session;

use function ceil;
use function explode;
use function is_numeric;
use function json_encode;
use function max;
use function mb_strlen;
use function number_format;
use function preg_match;
use function time;

final class Forum
{
    private $topicsRead = [];

    private $forumsRead = [];

    private $forumReadTime = 0;

    private $numperpage = 20;

    /**
     * @var float|int
     */
    private $pageNumber = 0;

    public function __construct(
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Session $session,
    ) {
        $this->page->loadmeta('forum');
    }

    public function route(): void
    {
        if (
            isset($this->jax->b['page'])
            && is_numeric($this->jax->b['page'])
            && $this->jax->b['page'] > 0
        ) {
            $this->pageNumber = $this->jax->b['page'] - 1;
        }

        preg_match('@^([a-zA-Z_]+)(\d+)$@', (string) $this->jax->g['act'], $act);
        if (isset($this->jax->b['markread']) && $this->jax->b['markread']) {
            $this->markread($act[2]);
            $this->page->location('?');

            return;
        }

        if ($this->page->jsupdate) {
            $this->update();

            return;
        }

        if (
            isset($this->jax->b['replies'])
            && is_numeric($this->jax->b['replies'])
        ) {
            if (!$this->page->jsaccess) {
                $this->page->location('?');
            }

            $this->getreplysummary($this->jax->b['replies']);

            return;
        }

        $this->viewforum($act[2]);
    }

    public function viewforum($fid)
    {
        global $PERMS,$USER;

        // If no fid supplied, go to the index and halt execution.
        if (!$fid) {
            return $this->page->location('?');
        }

        $page = '';
        $rows = '';
        $table = '';
        $unread = false;

        $result = $this->database->safespecial(
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
        $fdata = $this->database->arow($result);
        $this->database->disposeresult($result);

        if (!$fdata) {
            $this->page->JS('alert', $this->database->error());

            return $this->page->location('?');
        }

        if ($fdata['redirect']) {
            $this->page->JS('softurl');
            $this->database->safespecial(
                <<<'EOT'
                    UPDATE %t
                    SET `redirects` = `redirects` + 1
                    WHERE `id`=?
                    EOT
                ,
                ['forums'],
                $this->database->basicvalue($fid),
            );

            return $this->page->location($fdata['redirect']);
        }

        $title = &$fdata['title'];

        $fdata['perms'] = $this->jax->parseperms(
            $fdata['perms'],
            $USER ? $USER['group_id'] : 3,
        );
        if (!$fdata['perms']['read']) {
            $this->page->JS('alert', 'no permission');

            return $this->page->location('?');
        }

        // NOW we can actually start building the page
        // subforums
        // right now, this loop also fixes the number of pages to show in a forum
        // parent forum - subforum topics = total topics
        // I'm fairly sure this is faster than doing
        // `SELECT count(*) FROM topics`... but I haven't benchmarked it.
        $result = $this->database->safespecial(
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
        while ($f = $this->database->arow($result)) {
            $fdata['topics'] -= $f['topics'];
            if ($this->pageNumber) {
                continue;
            }

            $rows .= $this->page->meta(
                'forum-subforum-row',
                $f['id'],
                $f['title'],
                $f['subtitle'],
                $this->page->meta(
                    'forum-subforum-lastpost',
                    $f['lp_tid'],
                    $this->jax->pick($f['lp_topic'], '- - - - -'),
                    $f['lp_name'] ? $this->page->meta(
                        'user-link',
                        $f['lp_uid'],
                        $f['lp_gid'],
                        $f['lp_name'],
                    ) : 'None',
                    $this->jax->pick($this->jax->date($f['lp_date']), '- - - - -'),
                ),
                $f['topics'],
                $f['posts'],
                ($read = $this->isForumRead($f)) ? 'read' : 'unread',
                $read ? $this->jax->pick(
                    $this->page->meta(
                        'subforum-icon-read',
                    ),
                    $this->page->meta(
                        'icon-read',
                    ),
                ) : $this->jax->pick(
                    $this->page->meta('subforum-icon-unread'),
                    $this->page->meta('icon-unread'),
                ),
            );
            if ($read) {
                continue;
            }

            $unread = true;
        }

        if ($rows !== '' && $rows !== '0') {
            $page .= $this->page->collapsebox(
                'Subforums',
                $this->page->meta('forum-subforum-table', $rows),
            );
        }

        $rows = '';
        $table = '';

        // Generate pages.
        $numpages = ceil($fdata['topics'] / $this->numperpage);
        $forumpages = '';
        if ($numpages !== 0.0) {
            foreach ($this->jax->pages($numpages, $this->pageNumber + 1, 10) as $v) {
                $forumpages .= '<a href="?act=vf' . $fid . '&amp;page='
                    . $v . '"' . ($v - 1 === $this->pageNumber ? ' class="active"' : '')
                    . '>' . $v . '</a> · ';
            }
        }

        // Buttons.
        $forumbuttons = '&nbsp;'
            . ($fdata['perms']['start'] ? '<a href="?act=post&amp;fid=' . $fid . '">'
            . $this->page->meta(
                $this->page->metaexists('button-newtopic')
                ? 'button-newtopic' : 'forum-button-newtopic',
            ) . '</a>' : '');
        $page .= $this->page->meta(
            'forum-pages-top',
            $forumpages,
        ) . $this->page->meta(
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
        $result = $this->database->safespecial(
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
            $this->pageNumber * $this->numperpage,
            $this->numperpage,
        );

        while ($f = $this->database->arow($result)) {
            $pages = '';
            if ($f['replies'] > 9) {
                foreach ($this->jax->pages(ceil(($f['replies'] + 1) / 10), 1, 10) as $v) {
                    $pages .= "<a href='?act=vt" . $f['id']
                        . "&amp;page={$v}'>{$v}</a> ";
                }

                $pages = $this->page->meta('forum-topic-pages', $pages);
            }

            $read = false;
            $unread = false;
            $rows .= $this->page->meta(
                'forum-row',
                $f['id'],
                // 1
                $this->jax->wordfilter($f['title']),
                // 2
                $this->jax->wordfilter($f['subtitle']),
                // 3
                $this->page->meta('user-link', $f['auth_id'], $f['auth_gid'], $f['auth_name']),
                // 4
                $f['replies'],
                // 5
                number_format($f['views']),
                // 6
                $this->jax->date($f['lp_date']),
                // 7
                $this->page->meta('user-link', $f['lp_uid'], $f['lp_gid'], $f['lp_name']),
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
                $read ? $this->jax->pick(
                    $this->page->meta('topic-icon-read'),
                    $this->page->meta('icon-read'),
                )
                : $this->jax->pick(
                    $this->page->meta('topic-icon-unread'),
                    $this->page->meta('icon-read'),
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
        if (!$this->pageNumber && !$unread) {
            $this->markread($fid);
        }

        if ($rows !== '' && $rows !== '0') {
            $table = $this->page->meta('forum-table', $rows);
        } else {
            if ($this->pageNumber > 0) {
                return $this->page->location('?act=vf' . $fid);
            }

            if ($fdata['perms']['start']) {
                $table = $this->page->error(
                    "This forum is empty! Don't like it? "
                    . "<a href='?act=post&amp;fid=" . $fid . "'>Create a topic!</a>",
                );
            }
        }

        $page .= $this->page->meta('box', ' id="fid_' . $fid . '_listing"', $title, $table);
        $page .= $this->page->meta('forum-pages-bottom', $forumpages);
        $page .= $this->page->meta('forum-buttons-bottom', $forumbuttons);

        // Start building the nav path.
        $path[$fdata['cat']] = '?act=vc' . $fdata['cat_id'];
        if ($fdata['path']) {
            $pathids = explode(' ', (string) $fdata['path']);
            $forums = [];
            $result = $this->database->safeselect(
                ['id', 'title'],
                'forums',
                'WHERE `id` IN ?',
                $pathids,
            );
            while ($f = $this->database->arow($result)) {
                $forums[$f['id']] = [$f['title'], '?act=vf' . $f['id']];
            }

            foreach ($pathids as $v) {
                $path[$forums[$v][0]] = $forums[$v][1];
            }
        }

        $path[$title] = "?act=vf{$fid}";
        $this->page->updatepath($path);
        if ($this->page->jsaccess) {
            $this->page->JS('update', 'page', $page);
        } else {
            $this->page->append('PAGE', $page);
        }

        return null;
    }

    public function getreplysummary($tid): void
    {
        $result = $this->database->safespecial(
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
        while ($f = $this->database->arow($result)) {
            $page .= '<tr><td>' . $f['name'] . '</td><td>' . $f['replies'] . '</td></tr>';
        }

        $this->page->JS('softurl');
        $this->page->JS(
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
        global $USER;
        if (empty($this->topicsRead)) {
            $this->topicsRead = $this->jax->parsereadmarkers($this->session->topicsread);
        }

        if (empty($this->forumsRead)) {
            $fr = $this->jax->parsereadmarkers($this->session->forumsread);
            if (isset($fr[$fid])) {
                $this->forumReadTime = $fr[$fid];
            }
        }

        if (!isset($this->topicsRead[$topic['id']])) {
            $this->topicsRead[$topic['id']] = 0;
        }

        return $topic['lp_date'] <= $this->jax->pick(
            max($this->topicsRead[$topic['id']], $this->forumReadTime),
            $this->session->read_date,
            $USER && $USER['last_visit'],
        );
    }

    public function isForumRead($forum): bool
    {
        global $USER;
        if (!$this->forumsRead) {
            $this->forumsRead = $this->jax->parsereadmarkers($this->session->forumsread);
        }

        return $forum['lp_date'] <= $this->jax->pick(
            $this->forumsRead[$forum['id']] ?? null,
            $this->session->read_date,
            $USER && $USER['last_visit'],
        );
    }

    public function markread($id): void
    {
        $forumsread = $this->jax->parsereadmarkers($this->session->forumsread);
        $forumsread[$id] = time();
        $this->session->forumsread = json_encode($forumsread);
    }
}
