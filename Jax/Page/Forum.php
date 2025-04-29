<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

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

    private float|int $pageNumber = 0;

    public function __construct(
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Session $session,
        private readonly Request $request,
        private readonly TextFormatting $textFormatting,
        private readonly Template $template,
        private readonly User $user,
    ) {
        $this->template->loadMeta('forum');
    }

    public function render(): void
    {
        if (
            is_numeric($this->request->both('page'))
            && $this->request->both('page') > 0
        ) {
            $this->pageNumber = $this->request->both('page') - 1;
        }

        preg_match('@^([a-zA-Z_]+)(\d+)$@', (string) $this->request->get('act'), $act);
        if ($this->request->both('markread') !== null) {
            $this->markread($act[2]);
            $this->page->location('?');

            return;
        }

        if ($this->request->isJSUpdate()) {
            $this->update();

            return;
        }

        if (is_numeric($this->request->both('replies'))) {
            if (!$this->request->isJSAccess()) {
                $this->page->location('?');
            }

            $this->getreplysummary($this->request->both('replies'));

            return;
        }

        $this->viewforum($act[2]);
    }

    private function viewforum(string $fid): void
    {
        // If no fid supplied, go to the index and halt execution.
        if ($fid === '' || $fid === '0') {
            $this->page->location('?');

            return;
        }

        $page = '';
        $rows = '';
        $table = '';
        $unread = false;

        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT
                    f.`id` AS `id`,
                    f.`cat_id` AS `cat_id`,
                    f.`title` AS `title`,
                    f.`subtitle` AS `subtitle`,
                    f.`lp_uid` AS `lp_uid`,
                    UNIX_TIMESTAMP(f.`lp_date`) AS `lp_date`,
                    f.`lp_tid` AS `lp_tid`,
                    f.`lp_topic` AS `lp_topic`,
                    f.`path` AS `path`,
                    f.`show_sub` AS `show_sub`,
                    f.`redirect` AS `redirect`,
                    f.`topics` AS `topics`,
                    f.`posts` AS `posts`,
                    f.`order` AS `order`,
                    f.`perms` AS `perms`,
                    f.`orderby` AS `orderby`,
                    f.`nocount` AS `nocount`,
                    f.`redirects` AS `redirects`,
                    f.`trashcan` AS `trashcan`,
                    f.`mods` AS `mods`,
                    f.`show_ledby` AS `show_ledby`,
                    c.`title` AS `cat`
                FROM %t f
                LEFT JOIN %t c
                    ON f.`cat_id`=c.`id`
                WHERE f.`id`=? LIMIT 1
                SQL
            ,
            ['forums', 'categories'],
            $fid,
        );
        $fdata = $this->database->arow($result);
        $this->database->disposeresult($result);

        if (!$fdata) {
            $this->page->command('alert', $this->database->error());
            $this->page->location('?');

            return;
        }

        if ($fdata['redirect']) {
            $this->page->command('softurl');
            $this->database->safespecial(
                <<<'SQL'
                    UPDATE %t
                    SET `redirects` = `redirects` + 1
                    WHERE `id`=?
                    SQL
                ,
                ['forums'],
                $this->database->basicvalue($fid),
            );

            $this->page->location($fdata['redirect']);

            return;
        }

        $title = &$fdata['title'];

        $fdata['perms'] = $this->user->parseForumPerms($fdata['perms']);
        if (!$fdata['perms']['read']) {
            $this->page->command('alert', 'no permission');

            $this->page->location('?');

            return;
        }

        // NOW we can actually start building the page
        // subforums
        // right now, this loop also fixes the number of pages to show in a forum
        // parent forum - subforum topics = total topics
        // I'm fairly sure this is faster than doing
        // `SELECT count(*) FROM topics`... but I haven't benchmarked it.
        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT
                    f.`id` AS `id`,
                    f.`cat_id` AS `cat_id`,
                    f.`title` AS `title`,
                    f.`subtitle` AS `subtitle`,
                    f.`lp_uid` AS `lp_uid`,
                    UNIX_TIMESTAMP(f.`lp_date`) AS `lp_date`,
                    f.`lp_tid` AS `lp_tid`,
                    f.`lp_topic` AS `lp_topic`,
                    f.`path` AS `path`,
                    f.`show_sub` AS `show_sub`,
                    f.`redirect` AS `redirect`,
                    f.`topics` AS `topics`,
                    f.`posts` AS `posts`,
                    f.`order` AS `order`,
                    f.`perms` AS `perms`,
                    f.`orderby` AS `orderby`,
                    f.`nocount` AS `nocount`,
                    f.`redirects` AS `redirects`,
                    f.`trashcan` AS `trashcan`,
                    f.`mods` AS `mods`,
                    f.`show_ledby` AS `show_ledby`,
                    m.`display_name` AS `lp_name`,
                    m.`group_id` AS `lp_gid`
                FROM %t f
                LEFT JOIN %t m
                ON f.`lp_uid`=m.`id`
                WHERE f.`path`=?
                    OR f.`path` LIKE ?
                ORDER BY f.`order`
                SQL
            ,
            ['forums', 'members'],
            $fid,
            "% {$fid}",
        );
        $rows = '';
        while ($forum = $this->database->arow($result)) {
            $fdata['topics'] -= $forum['topics'];
            if ($this->pageNumber) {
                continue;
            }

            $rows .= $this->template->meta(
                'forum-subforum-row',
                $forum['id'],
                $forum['title'],
                $forum['subtitle'],
                $this->template->meta(
                    'forum-subforum-lastpost',
                    $forum['lp_tid'],
                    $forum['lp_topic'] ?: '- - - - -',
                    $forum['lp_name'] ? $this->template->meta(
                        'user-link',
                        $forum['lp_uid'],
                        $forum['lp_gid'],
                        $forum['lp_name'],
                    ) : 'None',
                    $this->jax->date($forum['lp_date']) ?: '- - - - -',
                ),
                $forum['topics'],
                $forum['posts'],
                ($read = $this->isForumRead($forum)) ? 'read' : 'unread',
                $read ? (
                    $this->template->meta('subforum-icon-read')
                    ?: $this->template->meta('icon-read')
                ) : (
                    $this->template->meta('subforum-icon-unread')
                    ?: $this->template->meta('icon-unread')
                ),
            );
            if ($read) {
                continue;
            }

            $unread = true;
        }

        if ($rows !== '' && $rows !== '0') {
            $page .= $this->page->collapseBox(
                'Subforums',
                $this->template->meta('forum-subforum-table', $rows),
            );
        }

        $rows = '';
        $table = '';

        // Generate pages.
        $numpages = (int) ceil($fdata['topics'] / $this->numperpage);
        $forumpages = '';
        if ($numpages !== 0) {
            foreach ($this->jax->pages($numpages, $this->pageNumber + 1, 10) as $pageNumber) {
                $forumpages .= '<a href="?act=vf' . $fid . '&amp;page='
                    . $pageNumber . '"' . ($pageNumber - 1 === $this->pageNumber ? ' class="active"' : '')
                    . '>' . $pageNumber . '</a> · ';
            }
        }

        // Buttons.
        $forumbuttons = '&nbsp;'
            . ($fdata['perms']['start'] ? '<a href="?act=post&amp;fid=' . $fid . '">'
            . $this->template->meta(
                $this->template->metaExists('button-newtopic')
                ? 'button-newtopic' : 'forum-button-newtopic',
            ) . '</a>' : '');
        $page .= $this->template->meta(
            'forum-pages-top',
            $forumpages,
        ) . $this->template->meta(
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

        while ($forum = $this->database->arow($result)) {
            $pages = '';
            if ($forum['replies'] > 9) {
                foreach ($this->jax->pages((int) ceil(($forum['replies'] + 1) / 10), 1, 10) as $pageNumber) {
                    $pages .= "<a href='?act=vt" . $forum['id']
                        . "&amp;page={$pageNumber}'>{$pageNumber}</a> ";
                }

                $pages = $this->template->meta('forum-topic-pages', $pages);
            }

            $read = false;
            $unread = false;
            $rows .= $this->template->meta(
                'forum-row',
                $forum['id'],
                // 1
                $this->textFormatting->wordfilter($forum['title']),
                // 2
                $this->textFormatting->wordfilter($forum['subtitle']),
                // 3
                $this->template->meta('user-link', $forum['auth_id'], $forum['auth_gid'], $forum['auth_name']),
                // 4
                $forum['replies'],
                // 5
                number_format($forum['views']),
                // 6
                $this->jax->date($forum['lp_date']),
                // 7
                $this->template->meta('user-link', $forum['lp_uid'], $forum['lp_gid'], $forum['lp_name']),
                // 8
                ($forum['pinned'] ? 'pinned' : '') . ' ' . ($forum['locked'] ? 'locked' : ''),
                // 9
                $forum['summary'] ? $forum['summary'] . (mb_strlen((string) $forum['summary']) > 45 ? '...' : '') : '',
                // 10
                $this->user->getPerm('can_moderate') ? '<a href="?act=modcontrols&do=modt&tid='
                . $forum['id'] . '" class="moderate" onclick="RUN.modcontrols.togbutton(this)"></a>' : '',
                // 11
                $pages,
                // 12
                ($read = $this->isTopicRead($forum, $fid)) ? 'read' : 'unread',
                // 13
                $read ? (
                    $this->template->meta('topic-icon-read')
                    ?: $this->template->meta('icon-read')
                )
                : (
                    $this->template->meta('topic-icon-unread')
                    ?: $this->template->meta('icon-read')
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
            $table = $this->template->meta('forum-table', $rows);
        } else {
            if ($this->pageNumber > 0) {
                $this->page->location('?act=vf' . $fid);

                return;
            }

            if ($fdata['perms']['start']) {
                $table = $this->page->error(
                    "This forum is empty! Don't like it? "
                    . "<a href='?act=post&amp;fid=" . $fid . "'>Create a topic!</a>",
                );
            }
        }

        $page .= $this->template->meta('box', ' id="fid_' . $fid . '_listing"', $title, $table);
        $page .= $this->template->meta('forum-pages-bottom', $forumpages);
        $page .= $this->template->meta('forum-buttons-bottom', $forumbuttons);

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
            while ($forum = $this->database->arow($result)) {
                $forums[$forum['id']] = [$forum['title'], '?act=vf' . $forum['id']];
            }

            foreach ($pathids as $pathId) {
                $path[$forums[$pathId][0]] = $forums[$pathId][1];
            }
        }

        $path[$title] = "?act=vf{$fid}";
        $this->page->setBreadCrumbs($path);
        if ($this->request->isJSAccess()) {
            $this->page->command('update', 'page', $page);
        } else {
            $this->page->append('PAGE', $page);
        }
    }

    private function getreplysummary(string $tid): void
    {
        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT
                    m.`display_name` AS `name`,
                    COUNT(p.`id`) AS `replies`
                FROM %t p
                LEFT JOIN %t m
                    ON p.`auth_id`=m.`id`
                WHERE `tid`=?
                GROUP BY p.`auth_id`
                ORDER BY `replies` DESC
                SQL
            ,
            ['posts', 'members'],
            $tid,
        );
        $page = '';
        while ($summary = $this->database->arow($result)) {
            $page .= '<tr><td>' . $summary['name'] . '</td><td>' . $summary['replies'] . '</td></tr>';
        }

        $this->page->command('softurl');
        $this->page->command(
            'window',
            [
                'content' => '<table>' . $page . '</table>',
                'title' => 'Post Summary',
            ],
        );
    }

    private function update(): void
    {
        // Update the topic listing.
    }

    private function isTopicRead($topic, string $fid): bool
    {
        if (empty($this->topicsRead)) {
            $this->topicsRead = $this->jax->parsereadmarkers($this->session->get('topicsread'));
        }

        if (empty($this->forumsRead)) {
            $fr = $this->jax->parsereadmarkers($this->session->get('forumsread'));
            if (isset($fr[$fid])) {
                $this->forumReadTime = $fr[$fid];
            }
        }

        if (!isset($this->topicsRead[$topic['id']])) {
            $this->topicsRead[$topic['id']] = 0;
        }

        return $topic['lp_date'] <= (
            max($this->topicsRead[$topic['id']], $this->forumReadTime)
            ?: $this->session->get('read_date')
            ?: $this->user->get('last_visit')
        );
    }

    private function isForumRead($forum): bool
    {
        if (!$this->forumsRead) {
            $this->forumsRead = $this->jax->parsereadmarkers($this->session->get('forumsread'));
        }

        return $forum['lp_date'] <= (
            $this->forumsRead[$forum['id']]
            ?: $this->session->get('read_date')
            ?: $this->user->get('last_visit')
        );
    }

    private function markread(string $id): void
    {
        $forumsread = $this->jax->parsereadmarkers($this->session->get('forumsread'));
        $forumsread[$id] = time();
        $this->session->set('forumsread', json_encode($forumsread));
    }
}
