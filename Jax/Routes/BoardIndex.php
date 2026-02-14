<?php

declare(strict_types=1);

namespace Jax\Routes;

use Carbon\Carbon;
use Jax\Config;
use Jax\Database\Database;
use Jax\Date;
use Jax\Interfaces\Route;
use Jax\Lodash;
use Jax\Models\Category;
use Jax\Models\Forum;
use Jax\Models\Group;
use Jax\Models\Member;
use Jax\Models\Stats;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\User;
use Jax\UserOnline;
use Jax\UsersOnline;
use Override;

use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_map;
use function array_unique;
use function count;
use function explode;
use function implode;
use function json_decode;
use function max;
use function mb_substr;

use const JSON_THROW_ON_ERROR;
use const SORT_REGULAR;

final class BoardIndex implements Route
{
    /**
     * @var ?array<int,int> Map of forum IDs to their last read timestamp
     */
    private ?array $forumsread = null;

    /**
     * @var array<int,Member> List of all moderators by ID
     */
    private array $mods = [];

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly Date $date,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Session $session,
        private readonly Template $template,
        private readonly User $user,
        private readonly UsersOnline $usersOnline,
    ) {}

    #[Override]
    public function route($params): void
    {
        match (true) {
            $this->request->both('markread') !== null => $this->markEverythingRead(),
            $this->request->isJSUpdate() => $this->update(),
            default => $this->viewBoardIndex(),
        };
    }

    private function markEverythingRead(): void
    {
        $this->page->command('preventNavigation');
        $this->session->set('forumsread', '{}');
        $this->session->set('topicsread', '{}');
        $this->session->set('readDate', $this->database->datetime(Carbon::now('UTC')->getTimestamp()));
    }

    /**
     * Returns top level forums.
     *
     * @return array<Forum>
     */
    private function fetchIndexForums(): array
    {
        $forums = Forum::selectMany(<<<'SQL'
                WHERE `path` = ""
                ORDER BY `order`, `title` ASC
            SQL);

        return array_filter(
            $forums,
            fn(Forum $forum): bool => !$forum->perms || $this->user->getForumPerms($forum->perms)['view'],
        );
    }

    /**
     * @param array<Forum> $forums
     *
     * @return array<int,Member>
     */
    private function fetchLastPostMembers(array $forums): array
    {
        return Member::joinedOn($forums, static fn(Forum $forum): ?int => $forum->lastPostUser);
    }

    private function viewBoardIndex(): void
    {
        $this->session->set('locationVerbose', 'Viewing board index');

        $forums = $this->fetchIndexForums();
        $lastPostMembers = $this->fetchLastPostMembers($forums);
        $forumsByCatID = Lodash::groupBy($forums, static fn(Forum $forum): int => $forum->category ?? 0);

        $this->setModsFromForums($forums);

        $categories = Category::selectMany('ORDER BY `order`,`title` ASC');
        $categoryHTML = [];
        foreach ($categories as $category) {
            if (!array_key_exists($category->id, $forumsByCatID)) {
                continue;
            }

            $categoryHTML[] = $this->page->collapseBox(
                $category->title,
                $this->template->render('idx/table', [
                    'rows' => array_map(fn(Forum $forum): array => [
                        'forum' => $forum,
                        'lastPostHTML' => $this->formatLastPost(
                            $forum,
                            $forum->lastPostUser ? $lastPostMembers[$forum->lastPostUser] : null,
                        ),
                        'isRead' => $this->isForumRead($forum),
                        'mods' => $this->getMods($forum->mods),
                    ], $forumsByCatID[$category->id]),
                ]),
                'cat_' . $category->id,
            );
        }

        $page = $this->template->render('idx/index', [
            'categories' => $categoryHTML,
            'forumsByCatID' => $forumsByCatID,
            'boardStats' => $this->getBoardStats(),
        ]);

        if ($this->request->isJSNewLocation()) {
            $this->page->command('update', 'page', $page);
        } else {
            $this->page->append('PAGE', $page);
        }
    }

    /**
     * Selects Member records for all forum moderators and stores them.
     *
     * @param array<Forum> $forums
     */
    private function setModsFromForums(array $forums): void
    {
        $modIDs = [];
        $forumsWithMods = array_filter(
            $forums,
            static fn(Forum $forum): bool => $forum->showLedBy && $forum->mods !== '',
        );

        foreach ($forumsWithMods as $forumWithMod) {
            foreach (explode(',', $forumWithMod->mods) as $modId) {
                if ($modId === '') {
                    continue;
                }

                $modIDs[] = (int) $modId;
            }
        }

        $modIDs = array_unique($modIDs, SORT_REGULAR);

        $this->mods = $modIDs === []
            ? []
            : Lodash::keyBy(
                Member::selectMany(Database::WHERE_ID_IN, $modIDs),
                static fn(Member $member): int => $member->id,
            );
    }

    /**
     * @param string $modids a comma separated list
     *
     * @return array<Member>
     */
    private function getMods(string $modids): array
    {
        $returnedMods = [];

        foreach (explode(',', $modids) as $modId) {
            if (!array_key_exists($modId, $this->mods)) {
                continue;
            }

            $returnedMods[] = $this->mods[$modId];
        }

        return $returnedMods;
    }

    private function update(): void
    {
        $this->updateStats();
        $this->updateLastPosts();
    }

    private function getBoardStats(): string
    {
        if (!$this->user->getGroup()?->canViewStats) {
            return '';
        }

        $stats = Stats::selectOne();
        $lastRegisteredMember = $stats?->last_register !== null ? Member::selectOne($stats?->last_register) : null;

        $usersOnline = $this->usersOnline->getUsersOnline();
        $usersOnlineCount = count(array_filter(
            $usersOnline,
            static fn(UserOnline $userOnline): bool => !$userOnline->isBot,
        ));
        $legendGroups = Group::selectMany('WHERE `legend`=1 ORDER BY `title`');

        return $this->template->render('idx/stats', [
            'canModerate' => $this->user->isModerator(),
            'guestCount' => $this->usersOnline->getGuestCount(),
            'lastRegisteredMember' => $lastRegisteredMember,
            'legend' => $legendGroups,
            'stats' => $stats,
            'usersOnline' => $usersOnline,
            'usersOnlineCount' => $usersOnlineCount,
            'usersOnlineToday' => $this->usersOnline->getUsersOnlineToday(),
        ]);
    }

    private function updateStats(): void
    {
        $list = [];
        $oldcache = null;
        if ($this->session->get()->usersOnlineCache !== '') {
            $oldcache = array_flip(explode(',', $this->session->get()->usersOnlineCache));
        }

        $useronlinecache = '';
        foreach ($this->usersOnline->getUsersOnline() as $userOnline) {
            $lastUpdateTS = $this->session->get()->lastUpdate !== null
                ? $this->date->datetimeAsTimestamp($this->session->get()->lastUpdate)
                : 0;
            $lastActionIdle = $lastUpdateTS - ($this->config->getSetting('timetoidle') ?? 300) - 30;
            if (!$userOnline->uid && !$userOnline->isBot) {
                continue;
            }

            if (
                $userOnline->lastAction >= $lastUpdateTS
                || $userOnline->status === 'idle' && $userOnline->lastAction > $lastActionIdle
            ) {
                $list[] = $userOnline;
            }

            if ($oldcache !== null && $userOnline->uid) {
                unset($oldcache[$userOnline->uid]);
            }

            $useronlinecache .= $userOnline->uid . ',';
        }

        if ($oldcache !== null && $oldcache !== []) {
            $this->page->command('setoffline', implode(',', array_flip($oldcache)));
        }

        $this->session->set('usersOnlineCache', mb_substr($useronlinecache, 0, -1));
        if ($list === []) {
            return;
        }

        $this->page->command('onlinelist', $list);
    }

    private function updateLastPosts(): void
    {
        $unreadForums = array_filter(
            $this->fetchIndexForums(),
            fn(Forum $forum): bool => (
                !$this->isForumRead($forum)
                && $this->date->datetimeAsTimestamp($forum->lastPostDate) > $this->date->datetimeAsTimestamp($this->session->get()->lastUpdate)
            ),
        );

        $lastPostMembers = $this->fetchLastPostMembers($unreadForums);

        foreach ($unreadForums as $unreadForum) {
            $forumSelector = "#fid_{$unreadForum->id}";
            $this->page->command('addclass', $forumSelector, 'unread');
            $this->page->command('update', "{$forumSelector}_icon", $this->template->render('idx/icon-unread', [
                'forum' => $unreadForum,
            ]));
            $this->page->command(
                'update',
                "{$forumSelector}_lastpost",
                $this->formatLastPost(
                    $unreadForum,
                    $unreadForum->lastPostUser ? $lastPostMembers[$unreadForum->lastPostUser] : null,
                ),
                '1',
            );
            $this->page->command('update', "{$forumSelector}_topics", $this->template->render('idx/topics-count', [
                'count' => $unreadForum->topics,
            ]));
            $this->page->command('update', "{$forumSelector}_replies", $this->template->render('idx/replies-count', [
                'count' => $unreadForum->posts,
            ]));
        }
    }

    private function formatLastPost(Forum $forum, ?Member $member): string
    {
        return $this->template->render('idx/row-lastpost', [
            'forum' => $forum,
            'lastPostUser' => $member,
        ]);
    }

    private function isForumRead(Forum $forum): bool
    {
        if ($this->forumsread === null) {
            $this->forumsread = json_decode($this->session->get()->forumsread, true, flags: JSON_THROW_ON_ERROR);
        }

        if (!array_key_exists($forum->id, $this->forumsread ?? [])) {
            $this->forumsread[$forum->id] = 0;
        }

        return (
            $this->date->datetimeAsTimestamp($forum->lastPostDate) < max(
                $this->forumsread[$forum->id],
                $this->date->datetimeAsTimestamp($this->session->get()->readDate),
                $this->date->datetimeAsTimestamp($this->user->get()->lastVisit),
            )
        );
    }
}
