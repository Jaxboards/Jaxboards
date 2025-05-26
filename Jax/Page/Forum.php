<?php

declare(strict_types=1);

namespace Jax\Page;

use Carbon\Carbon;
use Jax\Database;
use Jax\Date;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_map;
use function array_reduce;
use function ceil;
use function explode;
use function is_numeric;
use function json_encode;
use function max;
use function mb_strlen;
use function number_format;
use function preg_match;

final class Forum
{
    /**
     * @var array<int,int> key: forumId, value: timestamp
     */
    private array $topicsRead = [];

    /**
     * @var array<int,int> key: forumId, value: timestamp
     */
    private array $forumsRead = [];

    private int $forumReadTime = 0;

    private int $numperpage = 20;

    private int $pageNumber = 0;

    public function __construct(
        private readonly Database $database,
        private readonly Date $date,
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
        $page = (int) $this->request->asString->both('page');

        if ($page > 0) {
            $this->pageNumber = $page - 1;
        }

        // Guaranteed to match here because of the router
        preg_match('@(\d+)$@', (string) $this->request->asString->get('act'), $act);
        if ($this->request->both('markRead') !== null) {
            $this->markRead((int) $act[1]);
            $this->page->location('?');

            return;
        }

        if (is_numeric($this->request->both('replies'))) {
            if (!$this->request->isJSAccess()) {
                $this->page->location('?');

                return;
            }

            $this->getReplySummary($this->request->both('replies'));

            return;
        }

        $this->viewForum((int) $act[1]);
    }

    private function viewForum(int $fid): void
    {
        // If no fid supplied, go to the index and halt execution.
        if ($fid === 0) {
            $this->page->location('?');

            return;
        }

        $page = '';
        $rows = '';
        $table = '';
        $unread = false;

        $result = $this->database->special(
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
                SQL,
            ['forums', 'categories'],
            $fid,
        );
        $fdata = $this->database->arow($result);
        $this->database->disposeresult($result);

        if (!$fdata) {
            $this->page->location('?');

            return;
        }

        if ($fdata['redirect']) {
            $this->page->command('softurl');
            $this->database->special(
                <<<'SQL'
                    UPDATE %t
                    SET `redirects` = `redirects` + 1
                    WHERE `id`=?
                    SQL,
                ['forums'],
                $fid,
            );

            $this->page->location($fdata['redirect']);

            return;
        }

        $title = &$fdata['title'];

        $fdata['perms'] = $this->user->getForumPerms($fdata['perms']);
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
        $result = $this->database->special(
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
                SQL,
            ['forums', 'members'],
            $fid,
            "% {$fid}",
        );
        $rows = '';
        foreach ($this->database->arows($result) as $forum) {
            $fdata['topics'] -= $forum['topics'];
            if ($this->pageNumber !== 0) {
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
                    $this->date->autoDate($forum['lp_date']) ?: '- - - - -',
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
        $result = $this->database->special(
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
                EOT,
            ['topics', 'members', 'members'],
            $fid,
            $this->pageNumber * $this->numperpage,
            $this->numperpage,
        );

        foreach ($this->database->arows($result) as $topic) {
            $pages = '';
            if ($topic['replies'] > 9) {
                foreach ($this->jax->pages((int) ceil(($topic['replies'] + 1) / 10), 1, 10) as $pageNumber) {
                    $pages .= "<a href='?act=vt" . $topic['id']
                        . "&amp;page={$pageNumber}'>{$pageNumber}</a> ";
                }

                $pages = $this->template->meta('forum-topic-pages', $pages);
            }

            $read = false;
            $unread = false;
            $rows .= $this->template->meta(
                'forum-row',
                $topic['id'],
                // 1
                $this->textFormatting->wordfilter($topic['title']),
                // 2
                $this->textFormatting->wordfilter($topic['subtitle']),
                // 3
                $this->template->meta('user-link', $topic['auth_id'], $topic['auth_gid'], $topic['auth_name']),
                // 4
                $topic['replies'],
                // 5
                number_format($topic['views']),
                // 6
                $this->date->autoDate($topic['lp_date']),
                // 7
                $this->template->meta('user-link', $topic['lp_uid'], $topic['lp_gid'], $topic['lp_name']),
                // 8
                ($topic['pinned'] ? 'pinned' : '') . ' ' . ($topic['locked'] ? 'locked' : ''),
                // 9
                $topic['summary'] ? $topic['summary'] . (mb_strlen((string) $topic['summary']) > 45 ? '...' : '') : '',
                // 10
                $this->user->getPerm('can_moderate') ? '<a href="?act=modcontrols&do=modt&tid='
                    . $topic['id'] . '" class="moderate" onclick="RUN.modcontrols.togbutton(this)"></a>' : '',
                // 11
                $pages,
                // 12
                ($read = $this->isTopicRead($topic, $fid)) ? 'read' : 'unread',
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
            $this->markRead($fid);
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
        $breadCrumbs = ["?act=vc{$fdata['cat_id']}" => $fdata['cat']];

        // Subforum breadcrumbs
        if ($fdata['path']) {
            $path = array_map(static fn($fid): int => (int) $fid, explode(' ', (string) $fdata['path']));
            $result = $this->database->select(
                ['id', 'title'],
                'forums',
                Database::WHERE_ID_IN,
                $path,
            );
            // This has to be two steps because WHERE ID IN(1,2,3)
            // does not select records in the same order
            $forumTitles = array_reduce(
                $this->database->arows($result),
                static function (array $forumTitles, array $forum) {
                    $forumTitles[$forum['id']] = $forum['title'];

                    return $forumTitles;
                },
                [],
            );
            foreach ($path as $pathId) {
                $breadCrumbs["?act=vf{$pathId}"] = $forumTitles[$pathId];
            }
        }

        $breadCrumbs["?act=vf{$fid}"] = $title;
        $this->page->setBreadCrumbs($breadCrumbs);
        if ($this->request->isJSAccess()) {
            $this->page->command('update', 'page', $page);
        } else {
            $this->page->append('PAGE', $page);
        }
    }

    private function getReplySummary(string $tid): void
    {
        $result = $this->database->special(
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
                SQL,
            ['posts', 'members'],
            $tid,
        );
        $page = '';
        foreach ($this->database->arows($result) as $summary) {
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

    /**
     * @param array<string,mixed> $topic
     */
    private function isTopicRead(array $topic, int $fid): bool
    {
        $topicId = (int) $topic['id'];
        if ($this->topicsRead === []) {
            $this->topicsRead = $this->jax->parseReadMarkers($this->session->get('topicsread'));
        }

        if ($this->forumsRead === []) {
            $forumsRead = $this->jax->parseReadMarkers($this->session->get('forumsread'));
            if (isset($forumsRead[$fid])) {
                $this->forumReadTime = $forumsRead[$fid];
            }
        }

        if (!isset($this->topicsRead[$topicId])) {
            $this->topicsRead[$topicId] = 0;
        }

        return $topic['lp_date'] <= (
            max($this->topicsRead[$topicId], $this->forumReadTime)
            ?: $this->session->get('read_date')
            ?: $this->user->get('last_visit')
        );
    }

    /**
     * @param array<string,mixed> $forum
     */
    private function isForumRead(array $forum): bool
    {
        if (!$this->forumsRead) {
            $this->forumsRead = $this->jax->parseReadMarkers($this->session->get('forumsread'));
        }

        return $forum['lp_date'] <= (
            $this->forumsRead[$forum['id']] ?? null
            ?: $this->session->get('read_date')
            ?: $this->user->get('last_visit')
        );
    }

    private function markRead(int $id): void
    {
        $forumsread = $this->jax->parseReadMarkers($this->session->get('forumsread'));
        $forumsread[$id] = Carbon::now()->getTimestamp();
        $this->session->set('forumsread', json_encode($forumsread));
    }
}
