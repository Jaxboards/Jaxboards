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
     * @var ?array<int,int> key: forumId, value: timestamp
     */
    private ?array $forumsRead = null;

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

        $forum = ModelsForum::selectOne($this->database, Database::WHERE_ID_EQUALS, $fid);

        if ($forum === null) {
            $this->page->location('?');

            return;
        }

        if ($forum->redirect !== '') {
            $this->page->command('softurl');

            ++$forum->redirects;
            $forum->update($this->database);

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
            default => 'lastPostDate',
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
                static fn(Topic $topic): ?int => $topic->author,
                $topics,
            ),
            array_map(
                static fn(Topic $topic): ?int => $topic->lastPostUser,
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

        $unread = false;
        foreach ($topics as $topic) {
            $pages = '';
            if ($topic->replies > 9) {
                foreach ($this->jax->pages((int) ceil(($topic->replies + 1) / 10), 1, 10) as $pageNumber) {
                    $pages .= "<a href='?act=vt" . $topic->id
                        . "&amp;page={$pageNumber}'>{$pageNumber}</a> ";
                }

                $pages = $this->template->meta('forum-topic-pages', $pages);
            }

            $author = $membersById[$topic->author];
            $lastPostAuthor = $membersById[$topic->lastPostUser] ?? null;

            $read = $this->isTopicRead($topic, $fid);
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
                    $author->groupID,
                    $author->displayName,
                ),
                // 4
                $topic->replies,
                // 5
                number_format($topic->views),
                // 6
                $topic->lastPostDate
                    ? $this->date->autoDate($topic->lastPostDate)
                    : '',
                // 7
                $lastPostAuthor ? $this->template->meta(
                    'user-link',
                    $lastPostAuthor->id,
                    $lastPostAuthor->groupID,
                    $lastPostAuthor->displayName,
                ) : '',
                // 8
                ($topic->pinned ? 'pinned' : '') . ' ' . ($topic->locked ? 'locked' : ''),
                // 9
                $topic->summary ? $topic->summary . (mb_strlen((string) $topic->summary) > 45 ? '...' : '') : '',
                // 10
                $this->user->getGroup()?->canModerate ? '<a href="?act=modcontrols&do=modt&tid='
                    . $topic->id . '" class="moderate" onclick="RUN.modcontrols.togbutton(this)"></a>' : '',
                // 11
                $pages,
                // 12
                $read ? 'read' : 'unread',
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
        if ($this->pageNumber === 0 && !$unread) {
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
        $category = Category::selectOne($this->database, Database::WHERE_ID_EQUALS, $forum->category);
        $breadCrumbs = $category !== null
            ? ["?act=vc{$forum->category}" => $category->title]
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

        $lastPostAuthors = Member::joinedOn(
            $this->database,
            $subforums,
            static fn($modelsForum): ?int => $modelsForum->lastPostUser,
        );

        // lastPostDate needs timestamp
        $rows = '';
        foreach ($subforums as $subforum) {
            $forum->topics -= $subforum->topics;

            if ($this->pageNumber !== 0) {
                continue;
            }

            $lastPostAuthor = $subforum->lastPostUser
                ? $lastPostAuthors[$subforum->lastPostUser]
                : null;

            $lastPostDate = $subforum->lastPostDate
                ? $this->date->autoDate($subforum->lastPostDate)
                : '- - - - -';

            $rows .= $this->template->meta(
                'forum-subforum-row',
                $subforum->id,
                $subforum->title,
                $subforum->subtitle,
                $this->template->meta(
                    'forum-subforum-lastpost',
                    $subforum->lastPostTopic,
                    $subforum->lastPostTopicTitle ?: '- - - - -',
                    $lastPostAuthor ? $this->template->meta(
                        'user-link',
                        $subforum->lastPostUser,
                        $lastPostAuthor->groupID,
                        $lastPostAuthor->displayName,
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
                    m.`displayName` AS `name`,
                    COUNT(p.`id`) AS `replies`
                FROM %t p
                LEFT JOIN %t m
                    ON p.`author`=m.`id`
                WHERE `tid`=?
                GROUP BY p.`author`
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
            $this->topicsRead = $this->jax->parseReadMarkers($this->session->get()->topicsread);
        }

        $forumReadTime = 0;
        if ($this->forumsRead === null) {
            $forumsRead = $this->jax->parseReadMarkers($this->session->get()->forumsread);
            if (array_key_exists($fid, $forumsRead)) {
                $forumReadTime = $forumsRead[$fid];
            }
        }

        if (!array_key_exists($topic->id, $this->topicsRead)) {
            $this->topicsRead[$topic->id] = 0;
        }

        $timestamp = $this->date->datetimeAsTimestamp(
            $topic->lastPostDate ?? $topic->date,
        );

        return $timestamp <= (
            (max($this->topicsRead[$topic->id], $forumReadTime) ?: $this->session->get()->readDate)
            ?: $this->user->get()->lastVisit
        );
    }

    private function isForumRead(ModelsForum $modelsForum): bool
    {
        if (!$modelsForum->lastPostDate) {
            return true;
        }


        if ($this->forumsRead === null) {
            $this->forumsRead = $this->jax->parseReadMarkers($this->session->get()->forumsread);
        }


        return $this->date->datetimeAsTimestamp($modelsForum->lastPostDate) <= (
            $this->forumsRead[$modelsForum->id] ?? null
            ?: $this->date->datetimeAsTimestamp($this->session->get()->readDate)
            ?: $this->date->datetimeAsTimestamp($this->user->get()->lastVisit)
        );
    }

    private function markRead(int $id): void
    {
        $forumsread = $this->jax->parseReadMarkers($this->session->get()->forumsread);
        $forumsread[$id] = Carbon::now('UTC')->getTimestamp();
        $this->session->set('forumsread', json_encode($forumsread));
    }
}
