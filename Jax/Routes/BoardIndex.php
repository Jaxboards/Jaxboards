<?php

declare(strict_types=1);

namespace Jax\Routes;

use Carbon\Carbon;
use Jax\Config;
use Jax\Database\Database;
use Jax\Date;
use Jax\Interfaces\Route;
use Jax\Models\Category;
use Jax\Models\Forum;
use Jax\Models\Group;
use Jax\Models\Member;
use Jax\Models\Stats;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;
use Jax\UserOnline;
use Jax\UsersOnline;

use function _\groupBy;
use function _\keyBy;
use function array_filter;
use function array_flip;
use function array_key_exists;
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
        private readonly Router $router,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly Template $template,
        private readonly User $user,
        private readonly UsersOnline $usersOnline,
    ) {
        $this->template->loadMeta('idx');
    }

    public function route($params): void
    {
        if ($this->request->both('markread') !== null) {
            $this->page->command('softurl');
            $this->session->set('forumsread', '{}');
            $this->session->set('topicsread', '{}');
            $this->session->set('readDate', $this->database->datetime(Carbon::now('UTC')->getTimestamp()));
        }

        if ($this->request->isJSUpdate()) {
            $this->update();
        } else {
            $this->viewBoardIndex();
        }
    }

    /**
     * Returns top level forums.
     *
     * @return array<Forum>
     */
    private function fetchIDXForums(): array
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
        return Member::joinedOn(
            $forums,
            static fn(Forum $forum): ?int => $forum->lastPostUser,
        );
    }

    private function viewBoardIndex(): void
    {
        $this->session->set('locationVerbose', 'Viewing board index');
        $page = '';

        $forums = $this->fetchIDXForums();
        $lastPostMembers = $this->fetchLastPostMembers($forums);
        $forumsByCatID = groupBy($forums, static fn(Forum $forum): ?int => $forum->category);

        $this->setModsFromForums($forums);

        $categories = Category::selectMany('ORDER BY `order`,`title` ASC');
        foreach ($categories as $category) {
            if (!array_key_exists($category->id, $forumsByCatID)) {
                continue;
            }

            $page .= $this->page->collapseBox(
                $category->title,
                $this->buildTable(
                    $forumsByCatID[$category->id],
                    $lastPostMembers,
                ),
                'cat_' . $category->id,
            );
        }

        $page .= $this->template->render(
            'idx/tools',
            [
                'markReadURL' => $this->router->url('index', ['markread' => '1']),
                'staffURL' => $this->router->url('members', ['filter' => 'staff', 'sortby' => 'g_title']),
            ],
        );

        $page .= $this->getBoardStats();

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
        foreach ($forums as $forum) {
            if (!$forum->showLedBy) {
                continue;
            }

            if (!$forum->mods) {
                continue;
            }

            foreach (explode(',', $forum->mods) as $modId) {
                if ($modId === '') {
                    continue;
                }

                $modIDs[] = (int) $modId;
            }
        }

        $modIDs = array_unique($modIDs, SORT_REGULAR);

        $this->mods = $modIDs === [] ? [] : keyBy(
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
            $returnedMods[] = $this->mods[$modId];
        }

        return $returnedMods;
    }

    /**
     * @param array<Forum>  $forums
     * @param array<Member> $lastPostMembers
     */
    private function buildTable(array $forums, array $lastPostMembers): string
    {
        $table = '';

        foreach ($forums as $forum) {
            $read = $this->isForumRead($forum);

            if ($forum->redirect) {
                $table .= $this->template->render(
                    'idx/redirect-row',
                    [
                        'forum' => $forum,
                        'forumURL' => $this->router->url('forum', [
                            'id' => $forum->id,
                            'slug' => $this->textFormatting->slugify($forum->title),
                        ]),
                    ],
                );
            } else {
                $markReadURL = $read
                    ? ''
                    : $this->router->url('forum', ['id' => $forum->id, 'markread' => 1]);
                $table .= $this->template->render(
                    'idx/row',
                    [
                        'forum' => $forum,
                        'forumURL' => $this->router->url('forum', [
                            'id' => $forum->id,
                            'slug' => $this->textFormatting->slugify($forum->title),
                        ]),
                        'lastPostHTML' => $this->formatLastPost(
                            $forum,
                            $lastPostMembers[$forum->lastPostUser] ?? null,
                        ),
                        'isRead' => $read,
                        'markReadURL' => $markReadURL,
                        'mods' => $forum->showLedBy && $forum->mods ? $this->getMods($forum->mods) : [],
                    ],
                );
            }
        }

        return $this->template->render('idx/table', [
            'rows' => $table,
        ]);
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
        $lastRegisteredMember = $stats?->last_register !== null
            ? Member::selectOne($stats->last_register)
            : null;

        $usersOnline = $this->usersOnline->getUsersOnline();
        $usersOnlineCount = count(array_filter($usersOnline, static fn(UserOnline $userOnline): bool => !$userOnline->isBot));
        $legendGroups = Group::selectMany('WHERE `legend`=1 ORDER BY `title`');

        return $this->template->render(
            'idx/stats',
            [
                'usersOnline' => $usersOnline,
                'usersOnlineCount' => $usersOnlineCount,
                'usersOnlineToday' => $this->usersOnline->getUsersOnlineToday(),
                'guestCount' => $this->usersOnline->getGuestCount(),
                'modURL' => $this->user->getGroup()?->canModerate !== 0
                    ? $this->router->url('modcontrols', ['do' => 'onlineSessions'])
                    : null,
                'stats' => $stats,
                'lastRegisteredMember' => $lastRegisteredMember,
                'legend' => $legendGroups,
            ],
        );
    }

    private function updateStats(): void
    {
        $list = [];
        $oldcache = null;
        if (
            $this->session->get()->usersOnlineCache !== ''
        ) {
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
                || $userOnline->status === 'idle'
                && $userOnline->lastAction > $lastActionIdle
            ) {
                $list[] = [
                    $userOnline->uid,
                    $userOnline->groupID,

                    $userOnline->status !== 'active'
                        ? $userOnline->status
                        : ($userOnline->birthday && ($this->config->getSetting('birthdays') & 1)
                            ? ' birthday' : ''),
                    $userOnline->name,
                    $userOnline->locationVerbose,
                    $userOnline->lastAction,
                ];
            }

            if ($oldcache !== null) {
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
            $this->fetchIDXForums(),
            fn(Forum $forum): bool => !$this->isForumRead($forum),
        );

        $lastPostMembers = $this->fetchLastPostMembers($unreadForums);

        foreach ($unreadForums as $unreadForum) {
            $this->page->command('addclass', '#fid_' . $unreadForum->id, 'unread');
            $this->page->command(
                'update',
                '#fid_' . $unreadForum->id . '_icon',
                $this->template->render('idx/icon-unread'),
            );
            $this->page->command(
                'update',
                '#fid_' . $unreadForum->id . '_lastpost',
                $this->formatLastPost($unreadForum, $lastPostMembers[$unreadForum->lastPostUser] ?? null),
                '1',
            );
            $this->page->command(
                'update',
                '#fid_' . $unreadForum->id . '_topics',
                $this->template->render('idx/topics-count', ['count' => $unreadForum->topics]),
            );
            $this->page->command(
                'update',
                '#fid_' . $unreadForum->id . '_replies',
                $this->template->render('idx/replies-count', ['count' => $unreadForum->posts]),
            );
        }
    }

    private function formatLastPost(Forum $forum, ?Member $member): string
    {
        return $this->template->render(
            'idx/row-lastpost',
            [
                'topicURL' => $this->router->url('topic', [
                    'id' => $forum->lastPostTopic,
                    'getlast' => '1',
                    'slug' => $this->textFormatting->slugify($forum->lastPostTopicTitle),
                ]),
                'lastPostTitle' => $this->textFormatting->wordfilter($forum->lastPostTopicTitle),
                'lastPostUser' => $member,
                'lastPostDate' => $forum->lastPostDate !== null
                    ? $this->date->autoDate($forum->lastPostDate)
                    : null,
            ],
        );
    }

    private function isForumRead(Forum $forum): bool
    {
        if ($this->forumsread === null) {
            $this->forumsread = json_decode($this->session->get()->forumsread, true, flags: JSON_THROW_ON_ERROR);
        }

        if (!array_key_exists($forum->id, $this->forumsread)) {
            $this->forumsread[$forum->id] = 0;
        }

        return $this->date->datetimeAsTimestamp($forum->lastPostDate) < max(
            $this->forumsread[$forum->id],
            $this->date->datetimeAsTimestamp($this->session->get()->readDate),
            $this->date->datetimeAsTimestamp($this->user->get()->lastVisit),
        );
    }
}
