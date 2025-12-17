<?php

declare(strict_types=1);

namespace Jax\Page;

use Carbon\Carbon;
use Jax\Database\Database;
use Jax\Date;
use Jax\Interfaces\Route;
use Jax\Jax;
use Jax\Models\Category;
use Jax\Models\Forum as ModelsForum;
use Jax\Models\Member;
use Jax\Models\Topic;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function _\keyBy;
use function array_all;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_reduce;
use function ceil;
use function explode;
use function implode;
use function in_array;
use function is_numeric;
use function json_decode;
use function json_encode;
use function max;
use function mb_strlen;
use function number_format;

use const JSON_THROW_ON_ERROR;

final class Forum implements Route
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
        private readonly Request $request,
        private readonly Router $router,
        private readonly Session $session,
        private readonly Template $template,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {
        $this->template->loadMeta('forum');
    }

    public function route($params): void
    {
        $page = (int) $this->request->asString->both('page');
        $replies = (int) $this->request->asString->both('replies');
        $markRead = $this->request->both('markread');
        $forumId = (int) $params['id'];

        if ($page > 0) {
            $this->pageNumber = $page - 1;
        }

        match(true) {
            $markRead !== null => $this->markForumAsRead($forumId),
            $replies !== 0 => $this->getReplySummary($replies),
            default => $this->viewForum($forumId),
        };
    }

    private function viewForum(int $fid): void
    {
        // If no fid supplied, go to the index and halt execution.
        if ($fid === 0) {
            $this->router->redirect('index');

            return;
        }

        $page = '';
        $table = '';

        $forum = ModelsForum::selectOne($fid);

        if ($forum === null) {
            $this->router->redirect('index');

            return;
        }

        if ($forum->redirect !== '') {
            $this->page->command('softurl');

            ++$forum->redirects;
            $forum->update();

            $this->router->redirect($forum->redirect);

            return;
        }

        $forumPerms = $this->user->getForumPerms($forum->perms);
        if (!$forumPerms['read']) {
            $this->page->command('alert', 'no permission');

            $this->router->redirect('index');

            return;
        }

        $this->setBreadCrumbs($forum);
        $this->page->setPageTitle($forum->title);

        // NOW we can actually start building the page
        // subforums
        // right now, this loop also fixes the number of pages to show in a forum
        // parent forum - subforum topics = total topics
        // I'm fairly sure this is faster than doing
        // `SELECT count(*) FROM topics`... but I haven't benchmarked it.
        $page .= $this->renderSubforums($forum);

        // Generate pages.
        $numpages = (int) ceil($forum->topics / $this->numperpage);
        $forumpages = '';
        if ($numpages !== 0) {
            $forumpages = implode(' · ', array_map(
                function (int $pageNumber) use ($forum): string {
                    $activeClass = ($pageNumber - 1 === $this->pageNumber ? 'class="active"' : '');
                    $pageURL = $this->router->url('forum', [
                        'id' => $forum->id,
                        'page' => $pageNumber,
                        'slug' => $this->textFormatting->slugify($forum->title),
                    ]);

                    return "<a href='{$pageURL}' {$activeClass}>{$pageNumber}</a> ";
                },
                $this->jax->pages($numpages, $this->pageNumber + 1, 10),
            ));
        }

        $newTopicURL = $this->router->url('post', ['fid' => $fid]);
        // Buttons.
        $forumbuttons = '&nbsp;'
            . ($forumPerms['start'] ? "<a href='{$newTopicURL}'>"
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

        $topics = Topic::selectMany(<<<SQL
            WHERE `fid`=?
            ORDER BY `pinned` DESC,{$orderby}
            LIMIT ?,?
            SQL,
            $fid,
            $this->pageNumber * $this->numperpage,
            $this->numperpage,
        );

        $topicListing = $this->renderTopicListing($forum, $topics);

        // If they're on the first page and all topics are read
        // mark the whole forum as read
        if (
            $this->pageNumber === 0
            && array_all($topics, $this->isTopicRead(...))
        ) {
            $this->markRead($fid);
        }

        $table = '';
        if ($topicListing !== '') {
            $table = $this->template->meta('forum-table', $topicListing);
        } else {
            if ($this->pageNumber > 0) {
                $this->router->redirect('forum', ['id' => $fid]);

                return;
            }

            if ($forumPerms['start']) {
                $newTopicURL = $this->router->url('post', ['fid' => $fid]);
                $table = $this->page->error(<<<HTML
                        This forum is empty! Don't like it? <a href='{$newTopicURL}'>Create a topic!</a>
                    HTML);
            }
        }

        $page .= $this->template->meta('box', ' id="fid_' . $fid . '_listing"', $forum->title, $table);
        $page .= $this->template->meta('forum-pages-bottom', $forumpages);
        $page .= $this->template->meta('forum-buttons-bottom', $forumbuttons);

        if ($this->request->isJSAccess()) {
            $this->page->command('update', 'page', $page);
        } else {
            $this->page->append('PAGE', $page);
        }
    }

    /**
     * @param array<Topic> $topics
     */
    private function renderTopicListing(ModelsForum $forum, array $topics): string
    {
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
                Database::WHERE_ID_IN,
                $memberIds,
            ),
            static fn(Member $member): int => $member->id,
        ) : [];

        $isForumModerator = $this->isForumModerator($forum);

        $html = '';
        foreach ($topics as $topic) {
            $pages = '';
            if ($topic->replies > 9) {
                $pageArray = [];
                foreach ($this->jax->pages((int) ceil(($topic->replies + 1) / 10), 1, 10) as $pageNumber) {
                    $pageURL = $this->router->url('topic', [
                        'id' => $topic->id,
                        'page' => $pageNumber,
                        'slug' => $this->textFormatting->slugify($topic->title),
                    ]);
                    $pageArray[] = "<a href='{$pageURL}'>{$pageNumber}</a>";
                }

                $pages = $this->template->meta('forum-topic-pages', implode(' ', $pageArray));
            }

            $author = $membersById[$topic->author];
            $lastPostAuthor = $membersById[$topic->lastPostUser] ?? null;
            $topicSlug = $this->textFormatting->slugify($topic->title);

            $read = $this->isTopicRead($topic);

            $html .= $this->template->meta(
                'forum-row',
                // 1
                $topic->id,
                // 2
                $this->textFormatting->wordfilter($topic->title),
                // 3
                $this->textFormatting->wordfilter($topic->subtitle),
                // 4
                $this->template->meta(
                    'user-link',
                    $author->id,
                    $author->groupID,
                    $author->displayName,
                ),
                // 5
                $topic->replies,
                // 6
                number_format($topic->views),
                // 7
                $topic->lastPostDate
                    ? $this->date->autoDate($topic->lastPostDate)
                    : '',
                // 8
                $lastPostAuthor ? $this->template->meta(
                    'user-link',
                    $lastPostAuthor->id,
                    $lastPostAuthor->groupID,
                    $lastPostAuthor->displayName,
                ) : '',
                // 9
                ($topic->pinned !== 0 ? 'pinned' : '') . ' ' . ($topic->locked !== 0 ? 'locked' : ''),
                // 10
                $topic->summary !== '' ? $topic->summary . (mb_strlen($topic->summary) > 45 ? '...' : '') : '',
                // 11
                $isForumModerator ? (
                    '<a href="'
                    . $this->router->url('modcontrols', ['do' => 'modt', 'tid' => $topic->id])
                    . '" class="moderate" onclick="RUN.modcontrols.togbutton(this)"></a>'
                ) : '',
                // 12
                $pages,
                // 13
                $read ? 'read' : 'unread',
                // 14
                $read ? (
                    $this->template->meta('topic-icon-read')
                    ?: $this->template->meta('icon-read')
                )
                    : (
                        $this->template->meta('topic-icon-unread')
                        ?: $this->template->meta('icon-read')
                    ),
                // 15
                $this->router->url('topic', ['id' => $topic->id, 'slug' => $topicSlug]),
                // 16
                $this->router->url('forum', ['id' => $topic->fid, 'replies' => $topic->id]),
                // 17
                $this->router->url('topic', [
                    'id' => $topic->id,
                    'getlast' => '1',
                    'slug' => $topicSlug,
                ]),
            );
        }

        return $html;
    }

    private function setBreadCrumbs(ModelsForum $forum): void
    {
        // Start building the nav path.
        $category = Category::selectOne($forum->category);
        $breadCrumbs = $category !== null
            ? [$this->router->url('category', ['id' => $forum->category]) => $category->title]
            : [];

        // Subforum breadcrumbs
        if ($forum->path !== '') {
            $path = array_map(static fn($fid): int => (int) $fid, explode(' ', $forum->path));
            $forums = ModelsForum::selectMany(Database::WHERE_ID_IN, $path);
            // This has to be two steps because WHERE ID IN(1,2,3)
            // does not select records in the same order
            $forumTitles = array_reduce(
                $forums,
                static function (array $forumTitles, ModelsForum $modelsForum): array {
                    $forumTitles[$modelsForum->id] = $modelsForum->title;

                    return $forumTitles;
                },
                [],
            );
            foreach ($path as $pathId) {
                $breadCrumbs[$this->router->url('forum', ['id' => $pathId])] = $forumTitles[$pathId];
            }
        }

        $breadCrumbs[$this->router->url('forum', ['id' => $forum->id])] = $forum->title;
        $this->page->setBreadCrumbs($breadCrumbs);
    }

    private function renderSubforums(ModelsForum $forum): string
    {
        $subforums = ModelsForum::selectMany(
            'WHERE path=? OR path LIKE ? '
            . 'ORDER BY `order`',
            (string) $forum->id,
            "% {$forum->id}",
        );

        $lastPostAuthors = Member::joinedOn(
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
            $subforumSlug = $this->textFormatting->slugify($subforum->title);

            $rows .= $this->template->meta(
                'forum-subforum-row',
                $subforum->id,
                $subforum->title,
                $subforum->subtitle,
                $this->template->meta(
                    'forum-subforum-lastpost',
                    $this->router->url('topic', [
                        'id' => $subforum->lastPostTopic,
                        'getlast' => '1',
                        'slug' => $this->textFormatting->slugify($subforum->lastPostTopicTitle),
                    ]),
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
                $this->router->url('forum', ['id' => $subforum->id, 'slug' => $subforumSlug]),
                $this->router->url('forum', ['id' => $subforum->id, 'markread' => '1', 'slug' => $subforumSlug]),
            );
        }

        return $rows !== '' ? $this->page->collapseBox(
            'Subforums',
            $this->template->meta('forum-subforum-table', $rows),
        ) : '';
    }

    private function getReplySummary(int $tid): void
    {
        if (!$this->request->isJSAccess()) {
            $this->router->redirect('index');

            return;
        }
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

    private function isTopicRead(Topic $topic): bool
    {
        if ($this->topicsRead === []) {
            $this->topicsRead = json_decode($this->session->get()->topicsread, true, flags: JSON_THROW_ON_ERROR);
        }

        $forumReadTime = 0;
        if ($this->forumsRead === null) {
            $forumsRead = json_decode($this->session->get()->forumsread, true, flags: JSON_THROW_ON_ERROR);
            if ($topic->fid && array_key_exists($topic->fid, $forumsRead)) {
                $forumReadTime = $forumsRead[$topic->fid];
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
            $this->forumsRead = json_decode($this->session->get()->forumsread, true, flags: JSON_THROW_ON_ERROR);
        }


        return $this->date->datetimeAsTimestamp($modelsForum->lastPostDate) <= (
            $this->forumsRead[$modelsForum->id] ?? null
            ?: $this->date->datetimeAsTimestamp($this->session->get()->readDate)
            ?: $this->date->datetimeAsTimestamp($this->user->get()->lastVisit)
        );
    }

    private function isForumModerator(ModelsForum $modelsForum): bool
    {
        return $this->user->getGroup()?->canModerate
            || in_array((string) $this->user->get()->id, explode(',', $modelsForum->mods), true);
    }

    private function markRead(int $id): void
    {
        $forumsread = json_decode($this->session->get()->forumsread, true, flags: JSON_THROW_ON_ERROR);
        $forumsread[$id] = Carbon::now('UTC')->getTimestamp();
        $this->session->set('forumsread', json_encode($forumsread, JSON_THROW_ON_ERROR));
    }

    private function markForumAsRead(int $id): void
    {
        $this->markRead($id);
        $this->router->redirect('index');
    }
}
