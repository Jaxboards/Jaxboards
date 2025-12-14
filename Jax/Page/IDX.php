<?php

declare(strict_types=1);

namespace Jax\Page;

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
use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function explode;
use function implode;
use function json_decode;
use function max;
use function mb_strlen;
use function mb_substr;
use function nl2br;
use function number_format;
use function preg_match;

use const SORT_REGULAR;

final class IDX implements Route
{
    /**
     * @var ?array<int,int> Map of forum IDs to their last read timestamp
     */
    private ?array $forumsread = null;

    /**
     * @var array<int> List of all moderator user IDs
     */
    private array $mods = [];

    /**
     * @var array<int,array<int>> a map of forum IDs to the subforums they contain
     */
    private array $subforumids = [];

    /**
     * @var array<int,string> map of forum IDs to their compiled HTML link lists
     */
    private array $subforums = [];

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
            $this->viewidx();
        }
    }

    /**
     * Returns top level forums.
     *
     * @return array<Forum>
     */
    private function fetchIDXForums(): array
    {
        $forums = Forum::selectMany(
            'WHERE `path` = "" '
            . 'ORDER BY `order`, `title` ASC',
        );

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

    private function viewidx(): void
    {
        $this->session->set('locationVerbose', 'Viewing board index');
        $page = '';

        $forums = $this->fetchIDXForums();
        $lastPostMembers = $this->fetchLastPostMembers($forums);
        $forumsByCatID = groupBy($forums, static fn(Forum $forum): ?int => $forum->category);

        foreach ($forums as $forum) {
            // Store subforum details for later.
            if ($forum->path) {
                preg_match('@\d+$@', $forum->path, $match);
                $subForumId = $match !== [] ? (int) $match[0] : null;
                if (
                    $subForumId
                    && array_key_exists($subForumId, $this->subforums)
                ) {
                    $this->subforumids[$subForumId][] = $forum->id;
                    $this->subforums[$subForumId] .= $this->template->meta(
                        'idx-subforum-link',
                        $this->router->url('forum', ['id' => $forum->id, 'slug' => $this->textFormatting->slugify($forum->title)]),
                        $forum->title,
                        $this->textFormatting->blockhtml($forum->subtitle),
                    ) . $this->template->meta('idx-subforum-splitter');
                }
            }

            // Store mod details for later.
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

                $this->mods[] = (int) $modId;
            }
        }

        // Remove duplicates
        $this->mods = array_unique($this->mods, SORT_REGULAR);
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

        $page .= $this->template->meta(
            'idx-tools',
            $this->router->url('index', ['markread' => '1']),
            $this->router->url('members', ['filter' => 'staff', 'sortby' => 'g_title']),
        );

        $page .= $this->getBoardStats();

        if ($this->request->isJSNewLocation()) {
            $this->page->command('update', 'page', $page);
        } else {
            $this->page->append('PAGE', $page);
        }
    }

    /**
     * @return array<mixed>
     */
    private function getsubs(int $forumId): array
    {
        if (
            !array_key_exists($forumId, $this->subforumids)
        ) {
            return [];
        }

        $forum = $this->subforumids[$forumId];
        foreach ($forum as $forumId) {
            if (!$this->subforumids[$forumId]) {
                continue;
            }

            $forum = array_merge($forum, $this->getsubs($forumId));
        }

        return $forum;
    }

    /**
     * @param string $modids a comma separated list
     */
    private function getmods(string $modids): string
    {
        static $moderatorinfo = null;

        if ($moderatorinfo === null) {
            $moderatorinfo = [];
            $members = $this->mods !== []
                ? Member::selectMany(Database::WHERE_ID_IN, $this->mods)
                : $this->mods;
            foreach ($members as $member) {
                $moderatorinfo[$member->id] = $this->template->meta(
                    'user-link',
                    $member->id,
                    $member->groupID,
                    $member->displayName,
                );
            }
        }

        $forum = '';
        foreach (explode(',', $modids) as $modId) {
            $forum .= $moderatorinfo[(int) $modId] . $this->template->meta('idx-ledby-splitter');
        }

        return mb_substr($forum, 0, -mb_strlen($this->template->meta('idx-ledby-splitter')));
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
            $subforumHTML = '';
            if (
                $forum->showSubForums >= 1
                && array_key_exists($forum->id, $this->subforums)
            ) {
                $subforumHTML = $this->subforums[$forum->id];
            }

            if ($forum->showSubForums === 2) {
                foreach ($this->getsubs($forum->id) as $i) {
                    $subforumHTML .= $this->subforums[$i];
                }
            }

            if ($forum->redirect) {
                $table .= $this->template->meta(
                    'idx-redirect-row',
                    $this->router->url('forum', ['id' => $forum->id]),
                    $forum->title,
                    nl2br($forum->subtitle, false),
                    'Redirects: ' . $forum->redirects,
                    $this->template->meta('icon-redirect')
                        ?: $this->template->meta('idx-icon-redirect'),
                );
            } else {
                $forumId = $forum->id;
                $markReadURL = $read
                    ? ''
                    : $this->router->url('forum', ['id' => $forum->id, 'markread' => 1]);
                $linkText = $read
                    ? (
                        $this->template->meta('icon-read')
                        ?: $this->template->meta('idx-icon-read')
                    ) : (
                        $this->template->meta('icon-unread')
                        ?: $this->template->meta('idx-icon-unread')
                    );
                $table .= $this->template->meta(
                    'idx-row',
                    $forum->id,
                    $this->textFormatting->wordfilter($forum->title),
                    nl2br($forum->subtitle, false),
                    $subforumHTML !== ''
                        ? $this->template->meta(
                            'idx-subforum-wrapper',
                            mb_substr(
                                $subforumHTML,
                                0,
                                -1 * mb_strlen(
                                    $this->template->meta('idx-subforum-splitter'),
                                ),
                            ),
                        ) : '',
                    $this->formatLastPost($forum, $lastPostMembers[$forum->lastPostUser] ?? null),
                    $this->template->meta('idx-topics-count', $forum->topics),
                    $this->template->meta('idx-replies-count', $forum->posts),
                    $read ? 'read' : 'unread',
                    <<<HTML
                        <a id="fid_{$forumId}_icon" href="{$markReadURL}">
                            {$linkText}
                        </a>
                        HTML,
                    $forum->showLedBy && $forum->mods
                        ? $this->template->meta(
                            'idx-ledby-wrapper',
                            $this->getmods($forum->mods),
                        ) : '',
                    $this->router->url('forum', [
                        'id' => $forum->id,
                        'slug' => $this->textFormatting->slugify($forum->title),
                    ]),
                );
            }
        }

        return $this->template->meta('idx-table', $table);
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

        $legend = '';
        $page = '';

        $stats = Stats::selectOne();
        $lastRegisteredMember = $stats?->last_register !== null
            ? Member::selectOne($stats->last_register)
            : null;

        $usersOnlineToday = $this->usersOnline->getUsersOnlineToday();

        $birthdaysEnabled = $this->config->getSetting('birthdays');

        $userstoday = implode(', ', array_map(function (UserOnline $userOnline) use ($birthdaysEnabled): string {
            $birthdayClass = $userOnline->birthday && $birthdaysEnabled
                ? 'birthday'
                : '';
            $lastOnline = $userOnline->hide
                ? $userOnline->readDate
                : $userOnline->lastUpdate;
            $lastOnlineDate = $this->date->relativeTime($lastOnline);
            $profileURL = $this->router->url('profile', ['id' => $userOnline->uid]);

            return <<<HTML
                <a href="{$profileURL}"
                    class="user{$userOnline->uid} mgroup{$userOnline->groupID} {$birthdayClass}"
                    title="Last online: {$lastOnlineDate}"
                    data-use-tooltip="true"
                    data-last-online="{$lastOnline}"
                    >{$userOnline->name}</a>
                HTML;
        }, $usersOnlineToday));

        $usersonline = $this->getUsersOnlineList();
        $groups = Group::selectMany('WHERE `legend`=1 ORDER BY `title`');

        foreach ($groups as $group) {
            $legend .= "<a href='?' class='mgroup {$group->id}'>{$group->title}</a> ";
        }

        return $page . $this->template->meta(
            'idx-stats',
            $usersonline[1],
            $usersonline[0],
            $usersonline[2],
            count($usersOnlineToday),
            $userstoday,
            number_format($stats->members ?? 0),
            number_format($stats->topics ?? 0),
            number_format($stats->posts ?? 0),
            $lastRegisteredMember !== null ? $this->template->meta(
                'user-link',
                $lastRegisteredMember->id,
                $lastRegisteredMember->groupID,
                $lastRegisteredMember->displayName,
            ) : '',
            $legend,
        );
    }

    /**
     * @return array{string,int,int}
     */
    private function getUsersOnlineList(): array
    {
        $html = '';
        $numMembers = 0;

        foreach ($this->usersOnline->getUsersOnline() as $userOnline) {
            $title = $this->textFormatting->blockhtml(
                $userOnline->locationVerbose ?: 'Viewing the board.',
            );
            if ($userOnline->isBot) {
                $html .= '<a class="user' . $userOnline->uid . '" '
                    . 'title="' . $title . '" data-use-tooltip="true">'
                    . $userOnline->name . '</a>';
            } else {
                ++$numMembers;
                $profileURL = $this->router->url('profile', ['id' => $userOnline->uid]);
                $idleStatus = $userOnline->status === 'idle'
                    ? "idle lastAction{$userOnline->lastAction}"
                    : '';
                $birthday = $userOnline->birthday && $this->config->getSetting('birthdays')
                    ? 'birthday'
                    : '';
                $html .= <<<HTML
                    <a
                        href="{$profileURL}"
                        class="user{$userOnline->uid} mgroup{$userOnline->groupID} {$idleStatus} {$birthday}"
                        title="{$title}"
                        data-use-tooltip="true">{$userOnline->name}</a>
                    HTML;
            }
        }

        return [$html, $numMembers, $this->usersOnline->getGuestCount()];
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
                $this->template->meta('icon-unread')
                    ?: $this->template->meta('idx-icon-unread'),
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
                $this->template->meta('idx-topics-count', $unreadForum->topics),
            );
            $this->page->command(
                'update',
                '#fid_' . $unreadForum->id . '_replies',
                $this->template->meta('idx-replies-count', $unreadForum->posts),
            );
        }
    }

    private function formatLastPost(Forum $forum, ?Member $member): string
    {
        return $this->template->meta(
            'idx-row-lastpost',
            $this->router->url('topic', [
                'id' => $forum->lastPostTopic,
                'getlast' => '1',
                'slug' => $this->textFormatting->slugify($forum->lastPostTopicTitle),
            ]),
            $this->textFormatting->wordfilter($forum->lastPostTopicTitle) ?: '- - - - -',
            $member !== null ? $this->template->meta(
                'user-link',
                $member->id,
                $member->groupID,
                $member->displayName,
            ) : 'None',
            $forum->lastPostDate !== null
                ? $this->date->autoDate($forum->lastPostDate)
                : '- - - - -',
        );
    }

    private function isForumRead(Forum $forum): bool
    {
        if ($this->forumsread === null) {
            $this->forumsread = json_decode($this->session->get()->forumsread, true);
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
