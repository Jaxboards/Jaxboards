<?php

declare(strict_types=1);

namespace Jax\Routes;

use Carbon\Carbon;
use Jax\Database\Database;
use Jax\Date;
use Jax\Interfaces\Route;
use Jax\Jax;
use Jax\Lodash;
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
use Override;

use function array_all;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_reduce;
use function ceil;
use function explode;
use function implode;
use function in_array;
use function json_decode;
use function json_encode;
use function max;

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

    private int $numperpage;

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
        $this->numperpage = $user->get()->itemsPerPage ?? 20;
    }

    #[Override]
    public function route($params): void
    {
        $page = (int) $this->request->asString->both('page');
        $replies = (int) $this->request->asString->both('replies');
        $markRead = $this->request->both('markread');
        $forumId = (int) $params['id'];

        if ($page > 0) {
            $this->pageNumber = $page - 1;
        }

        match (true) {
            $markRead !== null => $this->markForumAsRead($forumId),
            $replies !== 0 => $this->getReplySummary($replies),
            default => $this->viewForum($forumId),
        };
    }

    /**
     * View the forum listing.
     */
    private function viewForum(int $fid): void
    {
        // If no fid supplied, go to the index and halt execution.
        if ($fid === 0) {
            $this->router->redirect('index');

            return;
        }

        $forum = ModelsForum::selectOne($fid);

        if ($forum === null) {
            $this->router->redirect('index');

            return;
        }

        if ($forum->redirect !== '') {
            $this->page->command('preventNavigation');

            ++$forum->redirects;
            $forum->update();

            $this->router->redirect($forum->redirect);

            return;
        }

        $forumPerms = $this->user->getForumPerms($forum->perms);
        if (!$forumPerms['read']) {
            $this->page->command('error', 'no permission');

            $this->router->redirect('index');

            return;
        }

        $this->setBreadCrumbs($forum);
        $this->page->setPageTitle($forum->title);

        // TODO: remove this insane coupling between forums and subforums
        // NOW we can actually start building the page
        // subforums
        // right now, this loop also fixes the number of pages to show in a forum
        // parent forum - subforum topics = total topics
        // I'm fairly sure this is faster than doing
        // `SELECT count(*) FROM topics`... but I haven't benchmarked it.
        $subforums = $this->renderSubforums($forum);

        // Generate pages.
        $numpages = (int) ceil($forum->topics / $this->numperpage);
        $forumpages = '';
        if ($numpages !== 0) {
            $forumpages = implode(' · ', array_map(
                function (int $pageNumber) use ($forum): string {
                    $activeClass = ($pageNumber - 1) === $this->pageNumber ? 'class="active"' : '';
                    $pageURL = $this->router->url('forum', [
                        'id' => $forum->id,
                        'page' => $pageNumber,
                        'slug' => $this->textFormatting->slugify($forum->title),
                    ]);

                    return "<a href='{$pageURL}' {$activeClass}>{$pageNumber}</a> ";
                },
                $this->jax->pages($numpages, $this->pageNumber + 1),
            ));
        }

        $orderby =
            match ($forum->orderby & ~1) {
                2 => 'id',
                4 => 'title',
                default => 'lastPostDate',
            }
            . ' '
            . (($forum->orderby & 1) !== 0 ? 'ASC' : 'DESC');

        $topics = Topic::selectMany(<<<SQL
            WHERE `fid`=?
            ORDER BY `pinned` DESC,{$orderby}
            LIMIT ?,?
            SQL, $fid, $this->pageNumber * $this->numperpage, $this->numperpage);

        // If they're on the first page and all topics are read
        // mark the whole forum as read
        if ($this->pageNumber === 0 && array_all($topics, $this->isTopicRead(...))) {
            $this->markRead($fid);
        }

        $index = $this->template->render('forum/index', [
            'forum' => $forum,
            'forumPerms' => $forumPerms,
            'pages' => $forumpages,
            'subforums' => $subforums,
            'topicListing' => $this->template->render('global/box', [
                'boxID' => "fid_{$fid}_listing",
                'title' => $forum->title,
                'content' => $this->renderTopicListing($forum, $topics),
            ]),
        ]);

        if ($this->request->isJSAccess()) {
            $this->page->command('update', 'page', $index);
        } else {
            $this->page->append('PAGE', $index);
        }
    }

    /**
     * Renders all topics in the forum.
     *
     * @param array<Topic> $topics
     */
    private function renderTopicListing(ModelsForum $modelsForum, array $topics): string
    {
        $memberIds = $topics !== [] ? array_merge(...array_map(static fn(Topic $topic): array => [
            $topic->author,
            $topic->lastPostUser,
        ], $topics)) : [];

        $membersById = $memberIds !== []
            ? Lodash::keyBy(
                Member::selectMany(Database::WHERE_ID_IN, $memberIds),
                static fn(Member $member): int => $member->id,
            )
            : [];

        $isForumModerator = $this->isForumModerator($modelsForum);

        $rows = array_map(fn(Topic $topic): array => [
            'author' => $topic->author ? $membersById[$topic->author] : null,
            'topic' => $topic,
            'lastPostUser' => $topic->lastPostUser ? $membersById[$topic->lastPostUser] : null,
            'isRead' => $this->isTopicRead($topic),
            'isForumModerator' => $isForumModerator,
            'pages' => $this->renderTopicPages($topic),
        ], $topics);

        return $this->template->render('forum/table', [
            'forum' => $modelsForum,
            'rows' => $rows,
        ]);
    }

    /**
     * Renders page links next to topics.
     */
    private function renderTopicPages(Topic $topic): string
    {
        if ($topic->replies <= $this->numperpage) {
            return '';
        }

        $pageArray = [];
        foreach ($this->jax->pages((int) ceil(($topic->replies + 1) / $this->numperpage), 1) as $pageNumber) {
            $pageURL = $this->router->url('topic', [
                'id' => $topic->id,
                'page' => $pageNumber,
                'slug' => $this->textFormatting->slugify($topic->title),
            ]);
            $pageArray[] = "<a href='{$pageURL}'>{$pageNumber}</a>";
        }

        return $this->template->render('forum/topic-pages', [
            'pages' => implode(' ', $pageArray),
        ]);
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
            'WHERE path=? OR path LIKE ? ORDER BY `order`',
            (string) $forum->id,
            "% {$forum->id}",
        );

        $lastPostUsers = Member::joinedOn($subforums, static fn($modelsForum): ?int => $modelsForum->lastPostUser);

        // lastPostDate needs timestamp
        $rows = [];
        foreach ($subforums as $subforum) {
            $forum->topics -= $subforum->topics;

            if ($this->pageNumber !== 0) {
                continue;
            }

            $rows[] = [
                'subforum' => $subforum,
                'lastPostUser' => $subforum->lastPostUser ? $lastPostUsers[$subforum->lastPostUser] : null,
                'isRead' => $this->isForumRead($subforum),
            ];
        }

        return (
            $rows === []
            ? ''
            : $this->page->collapseBox(
                'Subforums',
                $this->template->render('forum/subforum-table', [
                    'rows' => $rows,
                ]),
                'subforums_' . $forum->id,
            )
        );
    }

    private function getReplySummary(int $tid): void
    {
        if (!$this->request->isJSAccess()) {
            $this->router->redirect('index');

            return;
        }

        $result = $this->database->special(<<<'SQL'
            SELECT
                m.`displayName` AS `name`,
                COUNT(p.`id`) AS `replies`
            FROM %t p
            LEFT JOIN %t m
                ON p.`author`=m.`id`
            WHERE `tid`=?
            GROUP BY p.`author`
            ORDER BY `replies` DESC
            SQL, ['posts', 'members'], $tid);
        $page = '';
        foreach ($this->database->arows($result) as $summary) {
            $page .= '<tr><td>' . $summary['name'] . '</td><td>' . $summary['replies'] . '</td></tr>';
        }

        $this->page->command('preventNavigation');
        $this->page->command('window', [
            'content' => '<table>' . $page . '</table>',
            'title' => 'Post Summary',
        ]);
    }

    private function isTopicRead(Topic $topic): bool
    {
        if ($this->topicsRead === []) {
            $this->topicsRead = json_decode($this->session->get()->topicsread, true, flags: JSON_THROW_ON_ERROR);
        }

        $forumReadTime = 0;
        if ($this->forumsRead === null) {
            $forumsRead = json_decode($this->session->get()->forumsread, true, flags: JSON_THROW_ON_ERROR);
            if ($topic->fid && array_key_exists((string) $topic->fid, $forumsRead)) {
                $forumReadTime = $forumsRead[$topic->fid];
            }
        }

        if (!array_key_exists($topic->id, $this->topicsRead)) {
            $this->topicsRead[$topic->id] = 0;
        }

        $timestamp = $this->date->datetimeAsTimestamp($topic->lastPostDate ?? $topic->date);

        return (
            $timestamp
            <= (
                max($this->topicsRead[$topic->id], $forumReadTime)
                ?: $this->session->get()->readDate ?: $this->user->get()->lastVisit
            )
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

        return (
            $this->date->datetimeAsTimestamp($modelsForum->lastPostDate)
            <= (
                $this->forumsRead[$modelsForum->id] ?? null ?: $this->date->datetimeAsTimestamp($this->session->get()->readDate) ?: $this->date->datetimeAsTimestamp($this->user->get()->lastVisit)
            )
        );
    }

    private function isForumModerator(ModelsForum $modelsForum): bool
    {
        if ($this->user->isModerator()) {
            return true;
        }

        return in_array((string) $this->user->get()->id, explode(',', $modelsForum->mods), true);
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
