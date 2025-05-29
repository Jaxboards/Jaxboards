<?php

declare(strict_types=1);

namespace Jax\Page;

use Carbon\Carbon;
use Jax\Database;
use Jax\Date;
use Jax\Jax;
use Jax\Models\Category;
use Jax\Models\Forum as ModelsForum;
use Jax\Models\Member;
use Jax\Models\Topic;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function _\keyBy;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_reduce;
use function ceil;
use function explode;
use function implode;
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
        if ($this->request->both('markread') !== null) {
            $this->markRead((int) $act[1]);

            if ($this->request->isJSAccess()) {
                $this->page->command('softurl');

                return;
            }

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

        $forum = ModelsForum::selectOne($this->database, Database::WHERE_ID_EQUALS, $fid);

        if ($forum === null) {
            $this->page->location('?');

            return;
        }

        if ($forum->redirect !== '') {
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

            $this->page->location($forum->redirect);

            return;
        }

        $forumPerms = $this->user->getForumPerms($forum->perms);
        if (!$forumPerms['read']) {
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
        $page .= $this->printSubforums($forum);

        $rows = '';
        $table = '';

        // Generate pages.
        $numpages = (int) ceil($forum->topics / $this->numperpage);
        $forumpages = '';
        if ($numpages !== 0) {
            $forumpages = implode(' · ', array_map(
                function ($pageNumber) use ($fid): string {
                    $activeClass = ($pageNumber - 1 === $this->pageNumber ? ' class="active"' : '');

                    return "<a href='?act=vf{$fid}&amp;page={$pageNumber}'{$activeClass}'>{$pageNumber}</a> ";
                },
                $this->jax->pages($numpages, $this->pageNumber + 1, 10),
            ));
        }

        // Buttons.
        $forumbuttons = '&nbsp;'
            . ($forumPerms['start'] ? '<a href="?act=post&amp;fid=' . $fid . '">'
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

        $orderby = match ($forum->orderby & ~1) {
            2 => 'id',
            4 => 'title',
            default => 'lp_date',
        } . ' ' . (
            ($forum->orderby & 1) !== 0
                ? 'ASC'
                : 'DESC'
        );

        $topics = Topic::selectMany(
            $this->database,
            'WHERE `fid`=? '
            . "ORDER BY `pinned` DESC,{$orderby} "
            . 'LIMIT ?,? ',
            $fid,
            $this->pageNumber * $this->numperpage,
            $this->numperpage,
        );

        $memberIds = array_merge(
            array_map(
                static fn(Topic $topic): ?int => $topic->auth_id,
                $topics,
            ),
            array_map(
                static fn(Topic $topic): ?int => $topic->lp_uid,
                $topics,
            ),
        );

        $membersById = $memberIds !== [] ? keyBy(
            Member::selectMany(
                $this->database,
                Database::WHERE_ID_IN,
                $memberIds,
            ),
            static fn(Member $member): int => $member->id,
        ) : [];

        foreach ($topics as $topic) {
            $pages = '';
            if ($topic->replies > 9) {
                foreach ($this->jax->pages((int) ceil(($topic->replies + 1) / 10), 1, 10) as $pageNumber) {
                    $pages .= "<a href='?act=vt" . $topic->id
                        . "&amp;page={$pageNumber}'>{$pageNumber}</a> ";
                }

                $pages = $this->template->meta('forum-topic-pages', $pages);
            }

            $author = $membersById[$topic->auth_id];
            $lastPostAuthor = $membersById[$topic->lp_uid] ?? null;

            $read = false;
            $unread = false;
            $rows .= $this->template->meta(
                'forum-row',
                $topic->id,
                // 1
                $this->textFormatting->wordfilter($topic->title),
                // 2
                $this->textFormatting->wordfilter($topic->subtitle),
                // 3
                $this->template->meta(
                    'user-link',
                    $author->id,
                    $author->group_id,
                    $author->display_name,
                ),
                // 4
                $topic->replies,
                // 5
                number_format($topic->views),
                // 6
                $topic->lp_date
                    ? $this->date->autoDate($this->database->datetimeAsTimestamp($topic->lp_date))
                    : '',
                // 7
                $lastPostAuthor ? $this->template->meta(
                    'user-link',
                    $lastPostAuthor->id,
                    $lastPostAuthor->group_id,
                    $lastPostAuthor->display_name,
                ) : '',
                // 8
                ($topic->pinned ? 'pinned' : '') . ' ' . ($topic->locked ? 'locked' : ''),
                // 9
                $topic->summary ? $topic->summary . (mb_strlen((string) $topic->summary) > 45 ? '...' : '') : '',
                // 10
                $this->user->getPerm('can_moderate') ? '<a href="?act=modcontrols&do=modt&tid='
                    . $topic->id . '" class="moderate" onclick="RUN.modcontrols.togbutton(this)"></a>' : '',
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

        if ($rows !== '') {
            $table = $this->template->meta('forum-table', $rows);
        } else {
            if ($this->pageNumber > 0) {
                $this->page->location('?act=vf' . $fid);

                return;
            }

            if ($forumPerms['start']) {
                $table = $this->page->error(
                    "This forum is empty! Don't like it? "
                        . "<a href='?act=post&amp;fid=" . $fid . "'>Create a topic!</a>",
                );
            }
        }

        $page .= $this->template->meta('box', ' id="fid_' . $fid . '_listing"', $forum->title, $table);
        $page .= $this->template->meta('forum-pages-bottom', $forumpages);
        $page .= $this->template->meta('forum-buttons-bottom', $forumbuttons);

        // Start building the nav path.
        $category = Category::selectOne($this->database, Database::WHERE_ID_EQUALS, $forum->cat_id);
        $breadCrumbs = $category !== null
            ? ["?act=vc{$forum->cat_id}" => $category->title]
            : [];

        // Subforum breadcrumbs
        if ($forum->path !== '') {
            $path = array_map(static fn($fid): int => (int) $fid, explode(' ', $forum->path));
            $forums = ModelsForum::selectMany($this->database, Database::WHERE_ID_IN, $path);
            // This has to be two steps because WHERE ID IN(1,2,3)
            // does not select records in the same order
            $forumTitles = array_reduce(
                $forums,
                static function (array $forumTitles, ModelsForum $modelsForum) {
                    $forumTitles[$modelsForum->id] = $modelsForum->title;

                    return $forumTitles;
                },
                [],
            );
            foreach ($path as $pathId) {
                $breadCrumbs["?act=vf{$pathId}"] = $forumTitles[$pathId];
            }
        }

        $breadCrumbs["?act=vf{$fid}"] = $forum->title;
        $this->page->setBreadCrumbs($breadCrumbs);
        if ($this->request->isJSAccess()) {
            $this->page->command('update', 'page', $page);
        } else {
            $this->page->append('PAGE', $page);
        }
    }

    private function printSubforums(ModelsForum $forum): string
    {
        $subforums = ModelsForum::selectMany(
            $this->database,
            'WHERE path=? OR path LIKE ? '
            . 'ORDER BY `order`',
            (string) $forum->id,
            "% {$forum->id}",
        );

        $lastPostAuthorIds = array_filter(
            array_map(
                static fn($modelsForum): ?int => $modelsForum->lp_uid,
                $subforums,
            ),
            static fn($lpuid): bool => (bool) $lpuid,
        );

        $lastPostAuthors = $lastPostAuthorIds !== [] ? keyBy(
            Member::selectMany(
                $this->database,
                Database::WHERE_ID_IN,
                $lastPostAuthorIds,
            ),
            static fn(Member $member): int => $member->id,
        ) : [];


        // lp_date needs timestamp
        $rows = '';
        foreach ($subforums as $subforum) {
            $forum->topics -= $subforum->topics;

            if ($this->pageNumber !== 0) {
                continue;
            }

            $lastPostAuthor = $subforum->lp_uid
                ? $lastPostAuthors[$subforum->lp_uid]
                : null;

            $lastPostDate = $subforum->lp_date
                ? $this->date->autoDate($this->database->datetimeAsTimestamp($subforum->lp_date))
                : '- - - - -';

            $rows .= $this->template->meta(
                'forum-subforum-row',
                $subforum->id,
                $subforum->title,
                $subforum->subtitle,
                $this->template->meta(
                    'forum-subforum-lastpost',
                    $subforum->lp_tid,
                    $subforum->lp_topic ?: '- - - - -',
                    $lastPostAuthor ? $this->template->meta(
                        'user-link',
                        $subforum->lp_uid,
                        $lastPostAuthor->group_id,
                        $lastPostAuthor->display_name,
                    ) : 'None',
                    $lastPostDate,
                ),
                $subforum->topics,
                $subforum->posts,
                ($read = $this->isForumRead($subforum)) ? 'read' : 'unread',
                $read ? (
                    $this->template->meta('subforum-icon-read')
                    ?: $this->template->meta('icon-read')
                ) : (
                    $this->template->meta('subforum-icon-unread')
                    ?: $this->template->meta('icon-unread')
                ),
            );
        }

        return $rows !== '' ? $this->page->collapseBox(
            'Subforums',
            $this->template->meta('forum-subforum-table', $rows),
        ) : '';
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

    private function isTopicRead(Topic $topic, int $fid): bool
    {
        if ($this->topicsRead === []) {
            $this->topicsRead = $this->jax->parseReadMarkers($this->session->get('topicsread'));
        }

        if ($this->forumsRead === []) {
            $forumsRead = $this->jax->parseReadMarkers($this->session->get('forumsread'));
            if (array_key_exists($fid, $forumsRead)) {
                $this->forumReadTime = $forumsRead[$fid];
            }
        }

        if (!array_key_exists($topic->id, $this->topicsRead)) {
            $this->topicsRead[$topic->id] = 0;
        }

        $timestamp = $this->database->datetimeAsTimestamp(
            $topic->lp_date ?? $topic->date
        );

        var_dump($timestamp - (
            max($this->topicsRead[$topic->id], 0)
        ));
        return $timestamp <= (
            max($this->topicsRead[$topic->id], $this->forumReadTime)
            ?: $this->session->get('read_date')
            ?: $this->user->get('last_visit')
        );
    }

    private function isForumRead(ModelsForum $modelsForum): bool
    {
        if (!$modelsForum->lp_date) {
            return true;
        }

        if (!$this->forumsRead) {
            $this->forumsRead = $this->jax->parseReadMarkers($this->session->get('forumsread'));
        }

        return $this->database->datetimeAsTimestamp($modelsForum->lp_date) <= (
            $this->forumsRead[$modelsForum->id] ?? null
            ?: $this->session->get('read_date')
            ?: $this->user->get('last_visit')
        );
    }

    private function markRead(int $id): void
    {
        $forumsread = $this->jax->parseReadMarkers($this->session->get('forumsread'));
        $forumsread[$id] = Carbon::now('UTC')->getTimestamp();
        $this->session->set('forumsread', json_encode($forumsread));
    }
}
