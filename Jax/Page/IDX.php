<?php

declare(strict_types=1);

namespace Jax\Page;

use Carbon\Carbon;
use Jax\Config;
use Jax\Database;
use Jax\Date;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;
use Jax\Models\Member;

use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function explode;
use function gmdate;
use function implode;
use function max;
use function mb_strlen;
use function mb_substr;
use function nl2br;
use function number_format;
use function preg_match;
use function sprintf;

final class IDX
{
    /**
     * @var array<int,int> Map of forum IDs to their last read timestamp
     */
    private array $forumsread = [];

    /**
     * @var array<int> List of all moderator user IDs
     */
    private array $mods = [];

    /**
     * A map of forum IDs to the subforums they contain.
     *
     * @var array<int,array<int>>
     */
    private array $subforumids = [];

    /**
     * Map of forum IDs to their compiled HTML link lists.
     *
     * @var array<int,string>
     */
    private array $subforums = [];

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly Date $date,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly Template $template,
        private readonly User $user,
    ) {
        $this->template->loadMeta('idx');
    }

    public function render(): void
    {
        if ($this->request->both('markread') !== null) {
            $this->page->command('softurl');
            $this->session->set('forumsread', '{}');
            $this->session->set('topicsread', '{}');
            $this->session->set('read_date', Carbon::now()->getTimestamp());
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
     * @return array<array<string,mixed>>
     */
    private function fetchIDXForums(): array
    {
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
                ORDER BY f.`order`, f.`title` ASC
                SQL,
            [
                'forums',
                'members',
            ],
        );

        return $this->database->arows($result);
    }

    private function viewidx(): void
    {
        $this->session->set('location_verbose', 'Viewing board index');
        $page = '';
        $forumsByCatID = [];

        // This while loop just grabs all of the data, displaying is done below.
        foreach ($this->fetchIDXForums() as $forum) {
            $perms = $this->user->getForumPerms($forum['perms']);
            if ($forum['perms'] && !$perms['view']) {
                continue;
            }

            // Store subforum details for later.
            if ($forum['path']) {
                preg_match('@\d+$@', (string) $forum['path'], $match);
                $subForumId = $match !== [] ? (int) $match[0] : null;
                if (
                    $subForumId
                    && array_key_exists($subForumId, $this->subforums)
                ) {
                    $this->subforumids[$subForumId][] = $forum['id'];
                    $this->subforums[$subForumId] .= $this->template->meta(
                        'idx-subforum-link',
                        $forum['id'],
                        $forum['title'],
                        $this->textFormatting->blockhtml($forum['subtitle']),
                    ) . $this->template->meta('idx-subforum-splitter');
                }
            } else {
                $forumsByCatID[$forum['cat_id']][] = $forum;
            }

            // Store mod details for later.
            if (!$forum['show_ledby']) {
                continue;
            }

            if (!$forum['mods']) {
                continue;
            }

            foreach (explode(',', (string) $forum['mods']) as $modId) {
                if ($modId === '') {
                    continue;
                }

                $this->mods[] = (int) $modId;
            }
        }

        // Remove duplicates
        $this->mods = array_unique($this->mods);
        $catq = $this->database->select(
            ['id', 'title', '`order`'],
            'categories',
            'ORDER BY `order`,`title` ASC',
        );
        foreach ($this->database->arows($catq) as $category) {
            if (!array_key_exists($category['id'], $forumsByCatID)) {
                continue;
            }

            $page .= $this->page->collapseBox(
                $category['title'],
                $this->buildTable(
                    $forumsByCatID[$category['id']],
                ),
                'cat_' . $category['id'],
            );
        }

        $page .= $this->template->meta('idx-tools');

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

        if (!$moderatorinfo) {
            $moderatorinfo = [];
            $members = Member::selectMany($this->database, Database::WHERE_ID_IN, $this->mods);
            foreach ($members as $member) {
                $moderatorinfo[$member->id] = $this->template->meta(
                    'user-link',
                    $member->id,
                    $member->group_id,
                    $member->display_name,
                );
            }
        }

        $forum = '';
        foreach (explode(',', $modids) as $modId) {
            $forum .= $moderatorinfo[$modId] . $this->template->meta('idx-ledby-splitter');
        }

        return mb_substr($forum, 0, -mb_strlen($this->template->meta('idx-ledby-splitter')));
    }

    /**
     * @param array<array<string,mixed>> $forums
     */
    private function buildTable($forums): string
    {
        $table = '';
        foreach ($forums as $forum) {
            $read = $this->isForumRead($forum);
            $subforumHTML = '';
            if (
                $forum['show_sub'] >= 1
                && isset($this->subforums[$forum['id']])
            ) {
                $subforumHTML = $this->subforums[$forum['id']];
            }

            if ($forum['show_sub'] === 2) {
                foreach ($this->getsubs($forum['id']) as $i) {
                    $subforumHTML .= $this->subforums[$i];
                }
            }

            if ($forum['redirect']) {
                $table .= $this->template->meta(
                    'idx-redirect-row',
                    $forum['id'],
                    $forum['title'],
                    nl2br((string) $forum['subtitle']),
                    'Redirects: ' . $forum['redirects'],
                    $this->template->meta('icon-redirect')
                        ?: $this->template->meta('idx-icon-redirect'),
                );
            } else {
                $forumId = $forum['id'];
                $hrefCode = $read
                    ? ''
                    : ' href="?act=vf' . $forum['id'] . '&amp;markread=1"';
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
                    $forum['id'],
                    $this->textFormatting->wordfilter($forum['title']),
                    nl2br((string) $forum['subtitle']),
                    $subforumHTML
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
                    $this->formatLastPost($forum),
                    $this->template->meta('idx-topics-count', $forum['topics']),
                    $this->template->meta('idx-replies-count', $forum['posts']),
                    $read ? 'read' : 'unread',
                    <<<HTML
                        <a id="fid_{$forumId}_icon"{$hrefCode}>
                            {$linkText}
                        </a>
                        HTML,
                    $forum['show_ledby'] && $forum['mods']
                        ? $this->template->meta(
                            'idx-ledby-wrapper',
                            $this->getmods($forum['mods']),
                        ) : '',
                );
            }
        }

        return $this->template->meta('idx-table', $table);
    }

    /**
     * @return array<array<string,mixed>>
     */
    private function fetchUsersOnlineToday(): array
    {
        $result = $this->database->special(
            <<<'SQL'
                SELECT
                    UNIX_TIMESTAMP(MAX(s.`last_update`)) AS `last_update`,
                    m.`id` AS `id`,
                    m.`group_id` AS `group_id`,
                    m.`display_name` AS `name`,
                    CONCAT(MONTH(m.`birthdate`),' ',DAY(m.`birthdate`)) AS `birthday`,
                    UNIX_TIMESTAMP(MAX(s.`read_date`)) AS `read_date`,
                    s.`hide` AS `hide`
                FROM %t s
                LEFT JOIN %t m
                    ON s.`uid`=m.`id`
                WHERE s.`uid` AND s.`hide` = 0
                GROUP BY m.`id`
                ORDER BY `name`
                SQL,
            ['session', 'members'],
        );

        return $this->database->arows($result);
    }

    private function update(): void
    {
        $this->updateStats();
        $this->updateLastPosts();
    }

    private function getBoardStats(): string
    {
        if (!$this->user->getPerm('can_view_stats')) {
            return '';
        }

        $legend = '';
        $page = '';
        $result = $this->database->special(
            <<<'SQL'
                SELECT
                    s.`posts` AS `posts`,
                    s.`topics` AS `topics`,
                    s.`members` AS `members`,
                    s.`most_members` AS `most_members`,
                    s.`most_members_day` AS `most_members_day`,
                    s.`last_register` AS `last_register`,
                    m.`group_id` AS `group_id`,
                    m.`display_name` AS `display_name`
                FROM %t s
                LEFT JOIN %t m
                ON s.`last_register`=m.`id`
                SQL,
            ['stats', 'members'],
        );
        $stats = $this->database->arow($result)
            ?? ['posts' => 0, 'topics' => 0, 'members' => 0, 'last_register' => 0, 'group_id' => 0, 'display_name' => ''];

        $this->database->disposeresult($result);

        $usersOnlineToday = $this->fetchUsersOnlineToday();

        $today = gmdate('n j');
        $birthdaysEnabled = $this->config->getSetting('birthdays');

        $userstoday = implode(', ', array_map(function (array $user) use ($today, $birthdaysEnabled): string {
            $birthdayClass = $user['birthday'] === $today
                && $birthdaysEnabled ? 'birthday' : '';
            $lastOnline = $user['hide']
                ? $user['read_date']
                : $user['last_update'];
            $lastOnlineDate = $this->date->relativeTime($lastOnline);

            return <<<HTML
                <a href="?act=vu{$user['id']}"
                    class="user{$user['id']} mgroup{$user['group_id']} {$birthdayClass}"
                    title="Last online: {$lastOnlineDate}"
                    data-use-tooltip="true"
                    data-last-online="{$lastOnline}"
                    >{$user['name']}</a>
                HTML;
        }, $usersOnlineToday));

        $usersonline = $this->getUsersOnlineList();
        $result = $this->database->select(
            ['id', 'title'],
            'member_groups',
            'WHERE `legend`=1 ORDER BY `title`',
        );
        foreach ($this->database->arows($result) as $group) {
            $legend .= '<a href="?" class="mgroup' . $group['id'] . '">'
                . $group['title'] . '</a> ';
        }

        return $page . $this->template->meta(
            'idx-stats',
            $usersonline[1],
            $usersonline[0],
            $usersonline[2],
            count($usersOnlineToday),
            $userstoday,
            number_format($stats['members']),
            number_format($stats['topics']),
            number_format($stats['posts']),
            $this->template->meta(
                'user-link',
                $stats['last_register'],
                $stats['group_id'],
                $stats['display_name'],
            ),
            $legend,
        );
    }

    /**
     * @return array{string,int,int}
     */
    private function getUsersOnlineList(): array
    {
        $html = '';
        $guests = 0;
        $numMembers = 0;

        foreach ($this->database->getUsersOnline($this->user->isAdmin()) as $user) {
            if ($user['uid'] === null || $user['uid'] === 0) {
                ++$guests;

                continue;
            }

            $title = $this->textFormatting->blockhtml(
                (string) $user['location_verbose'] ?: 'Viewing the board.',
            );
            if (isset($user['is_bot']) && $user['is_bot']) {
                $html .= '<a class="user' . $user['uid'] . '" '
                    . 'title="' . $title . '" data-use-tooltip="true">'
                    . $user['name'] . '</a>';
            } else {
                ++$numMembers;
                $html .= sprintf(
                    '<a href="?act=vu%1$s" class="user%1$s mgroup%2$s" '
                        . 'title="%4$s" data-use-tooltip="true">'
                        . '%3$s</a>',
                    $user['uid'],
                    $user['group_id']
                        . (
                            $user['status'] === 'idle'
                            ? " idle lastAction{$user['last_action']}"
                            : ''
                        )
                        . ($user['birthday'] && $this->config->getSetting('birthdays') ? ' birthday' : ''),
                    $user['name'],
                    $title,
                );
            }
        }

        return [$html, $numMembers, $guests];
    }

    private function updateStats(): void
    {
        $list = [];
        if ($this->session->get('users_online_cache')) {
            $oldcache = array_flip(explode(',', (string) $this->session->get('users_online_cache')));
        }

        $useronlinecache = '';
        foreach ($this->database->getUsersOnline($this->user->isAdmin()) as $user) {
            $lastActionIdle = (int) ($this->session->get('last_update') ?? 0) - ($this->config->getSetting('timetoidle') ?? 300) - 30;
            if (!$user['uid'] && !$user['is_bot']) {
                continue;
            }

            if (
                $user['last_action'] >= (int) $this->session->get('last_update')
                || $user['status'] === 'idle'
                && $user['last_action'] > $lastActionIdle
            ) {
                $list[] = [
                    $user['uid'],
                    $user['group_id'],

                    $user['status'] !== 'active'
                        ? $user['status']
                        : ($user['birthday'] && ($this->config->getSetting('birthdays') & 1)
                            ? ' birthday' : ''),
                    $user['name'],
                    $user['location_verbose'],
                    $user['last_action'],
                ];
            }

            if (isset($oldcache)) {
                unset($oldcache[$user['uid']]);
            }

            $useronlinecache .= $user['uid'] . ',';
        }

        if (isset($oldcache) && $oldcache !== []) {
            $this->page->command('setoffline', implode(',', array_flip($oldcache)));
        }

        $this->session->set('users_online_cache', mb_substr($useronlinecache, 0, -1));
        if ($list === []) {
            return;
        }

        $this->page->command('onlinelist', $list);
    }

    private function updateLastPosts(): void
    {
        $unreadForums = array_filter(
            $this->fetchIDXForums(),
            fn($forum): bool => !$this->isForumRead($forum),
        );
        foreach ($unreadForums as $unreadForum) {
            $this->page->command('addclass', '#fid_' . $unreadForum['id'], 'unread');
            $this->page->command(
                'update',
                '#fid_' . $unreadForum['id'] . '_icon',
                $this->template->meta('icon-unread')
                    ?: $this->template->meta('idx-icon-unread'),
            );
            $this->page->command(
                'update',
                '#fid_' . $unreadForum['id'] . '_lastpost',
                $this->formatLastPost($unreadForum),
                '1',
            );
            $this->page->command(
                'update',
                '#fid_' . $unreadForum['id'] . '_topics',
                $this->template->meta('idx-topics-count', $unreadForum['topics']),
            );
            $this->page->command(
                'update',
                '#fid_' . $unreadForum['id'] . '_replies',
                $this->template->meta('idx-replies-count', $unreadForum['posts']),
            );
        }
    }

    /**
     * @param array<string,mixed> $forum
     */
    private function formatLastPost(array $forum): string
    {
        return $this->template->meta(
            'idx-row-lastpost',
            $forum['lp_tid'],
            $this->textFormatting->wordfilter($forum['lp_topic']) ?: '- - - - -',
            $forum['lp_uid'] ? $this->template->meta(
                'user-link',
                $forum['lp_uid'],
                $forum['lp_gid'],
                $forum['lp_name'],
            ) : 'None',
            $this->date->autoDate($forum['lp_date']) ?: '- - - - -',
        );
    }

    /**
     * @param array<string,mixed> $forum
     */
    private function isForumRead(array $forum): bool
    {
        if (!$this->forumsread) {
            $this->forumsread = $this->jax->parseReadMarkers($this->session->get('forumsread'));
        }

        if (!isset($this->forumsread[$forum['id']])) {
            $this->forumsread[$forum['id']] = 0;
        }

        return $forum['lp_date'] < max(
            $this->forumsread[$forum['id']],
            $this->session->get('read_date'),
            $this->user->get('last_visit'),
        );
    }
}
