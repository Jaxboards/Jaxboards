<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\TextFormatting;
use Jax\User;

use function array_flip;
use function array_keys;
use function array_merge;
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
use function time;

/**
 * @psalm-api
 */
final class IDX
{
    private array $forumsread = [];

    private array $mods;

    private array $subforumids;

    private array $subforums;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {
        $this->page->loadmeta('idx');
    }

    public function render(): void
    {
        if ($this->request->both('markread') !== null) {
            $this->page->JS('softurl');
            $this->session->set('forumsread', '{}');
            $this->session->set('topicsread', '{}');
            $this->session->set('read_date', time());
        }

        if ($this->request->isJSUpdate()) {
            $this->update();
        } else {
            $this->viewidx();
        }
    }

    private function viewidx(): void
    {
        $this->session->set('location_verbose', 'Viewing board index');
        $page = '';
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
                ORDER BY f.`order`, f.`title` ASC
                SQL
            ,
            [
                'forums',
                'members',
            ],
        );
        $data = [];
        $this->subforums = [];
        $this->subforumids = [];
        $this->mods = [];

        // This while loop just grabs all of the data, displaying is done below.
        while ($forum = $this->database->arow($result)) {
            $perms = $this->user->parseForumPerms($forum['perms']);
            if ($forum['perms'] && !$perms['view']) {
                continue;
            }

            // Store subforum details for later.
            if ($forum['path']) {
                preg_match('@\d+$@', (string) $forum['path'], $match);
                if (isset($this->subforums[$match[0]])) {
                    $this->subforumids[$match[0]][] = $forum['id'];
                    $this->subforums[$match[0]] .= $this->page->meta(
                        'idx-subforum-link',
                        $forum['id'],
                        $forum['title'],
                        $this->textFormatting->blockhtml($forum['subtitle']),
                    ) . $this->page->meta('idx-subforum-splitter');
                }
            } else {
                $data[$forum['cat_id']][] = $forum;
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

                $this->mods[$modId] = 1;
            }
        }

        $this->mods = array_keys($this->mods);
        $catq = $this->database->safeselect(
            ['id', 'title', '`order`'],
            'categories',
            'ORDER BY `order`,`title` ASC',
        );
        while ($forum = $this->database->arow($catq)) {
            if (empty($data[$forum['id']])) {
                continue;
            }

            $page .= $this->page->collapsebox(
                $forum['title'],
                $this->buildTable(
                    $data[$forum['id']],
                ),
                'cat_' . $forum['id'],
            );
        }

        $page .= $this->page->meta('idx-tools');

        $page .= $this->getBoardStats();

        if ($this->request->isJSNewLocation()) {
            $this->page->JS('update', 'page', $page);
            $this->page->updatepath();
        } else {
            $this->page->append('PAGE', $page);
        }
    }

    private function getsubs($forumId)
    {
        if (
            !isset($this->subforumids[$forumId])
            || !$this->subforumids[$forumId]
        ) {
            return [];
        }

        $forum = $this->subforumids[$forumId];
        foreach ($forum as $modId) {
            if (!$this->subforumids[$modId]) {
                continue;
            }

            $forum = array_merge($forum, $this->getsubs($modId));
        }

        return $forum;
    }

    private function getmods($modids): string
    {
        static $moderatorinfo = null;

        if (!$moderatorinfo) {
            $moderatorinfo = [];
            $result = $this->database->safeselect(
                ['id', 'display_name', 'group_id'],
                'members',
                'WHERE `id` IN ?',
                $this->mods,
            );
            while ($member = $this->database->arow($result)) {
                $moderatorinfo[$member['id']] = $this->page->meta(
                    'user-link',
                    $member['id'],
                    $member['group_id'],
                    $member['display_name'],
                );
            }
        }

        $forum = '';
        foreach (explode(',', (string) $modids) as $modId) {
            $forum .= $moderatorinfo[$modId] . $this->page->meta('idx-ledby-splitter');
        }

        return mb_substr($forum, 0, -mb_strlen($this->page->meta('idx-ledby-splitter')));
    }

    private function buildTable($forums): ?string
    {
        if (!$forums) {
            return null;
        }

        $table = '';
        foreach ($forums as $forum) {
            $read = $this->isForumRead($forum);
            $sf = '';
            if (
                $forum['show_sub'] >= 1
                && isset($this->subforums[$forum['id']])
            ) {
                $sf = $this->subforums[$forum['id']];
            }

            if ($forum['show_sub'] === 2) {
                foreach ($this->getsubs($forum['id']) as $i) {
                    $sf .= $this->subforums[$i];
                }
            }

            if ($forum['redirect']) {
                $table .= $this->page->meta(
                    'idx-redirect-row',
                    $forum['id'],
                    $forum['title'],
                    nl2br((string) $forum['subtitle']),
                    'Redirects: ' . $forum['redirects'],
                    $this->jax->pick(
                        $this->page->meta('icon-redirect'),
                        $this->page->meta('idx-icon-redirect'),
                    ),
                );
            } else {
                $forumId = $forum['id'];
                $hrefCode = $read
                    ? ''
                    : ' href="?act=vf' . $forum['id'] . '&amp;markread=1"';
                $linkText = $read
                    ? $this->jax->pick(
                        $this->page->meta('icon-read'),
                        $this->page->meta('idx-icon-read'),
                    ) : $this->jax->pick(
                        $this->page->meta('icon-unread'),
                        $this->page->meta('idx-icon-unread'),
                    );
                $table .= $this->page->meta(
                    'idx-row',
                    $forum['id'],
                    $this->textFormatting->wordfilter($forum['title']),
                    nl2br((string) $forum['subtitle']),
                    $sf
                    ? $this->page->meta(
                        'idx-subforum-wrapper',
                        mb_substr(
                            (string) $sf,
                            0,
                            -1 * mb_strlen(
                                $this->page->meta('idx-subforum-splitter'),
                            ),
                        ),
                    ) : '',
                    $this->formatlastpost($forum),
                    $this->page->meta('idx-topics-count', $forum['topics']),
                    $this->page->meta('idx-replies-count', $forum['posts']),
                    $read ? 'read' : 'unread',
                    <<<HTML
                        <a id="fid_{$forumId}_icon"{$hrefCode}>
                            {$linkText}
                        </a>
                        HTML
                    ,
                    $forum['show_ledby'] && $forum['mods']
                        ? $this->page->meta(
                            'idx-ledby-wrapper',
                            $this->getmods($forum['mods']),
                        ) : '',
                );
            }
        }

        return $this->page->meta('idx-table', $table);
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
        $userstoday = '';
        $result = $this->database->safespecial(
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
                SQL
            ,
            ['stats', 'members'],
        );
        $stats = $this->database->arow($result);
        $this->database->disposeresult($result);

        $result = $this->database->safespecial(
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
                SQL
            ,
            ['session', 'members'],
        );
        $nuserstoday = 0;
        $today = gmdate('n j');
        while ($user = $this->database->arow($result)) {
            if (!$user['id']) {
                continue;
            }

            $fId = $user['id'];
            $fName = $user['name'];
            $fGroupId = $user['group_id'];
            $birthdayCode = $user['birthday'] === $today
                && $this->config->getSetting('birthdays') ? ' birthday' : '';
            $lastOnlineCode = $this->jax->date(
                $user['hide'] ? $user['read_date'] : $user['last_update'],
                false,
            );
            $userstoday
                .= <<<EOT
                    <a href="?act=vu{$fId}" class="user{$fId} mgroup{$fGroupId}{$birthdayCode}"
                        title="Last online: {$lastOnlineCode}" data-use-tooltip="true">{$fName}</a>
                    EOT;
            $userstoday .= ', ';
            ++$nuserstoday;
        }

        $userstoday = mb_substr($userstoday, 0, -2);
        $usersonline = $this->getUsersOnlineList();
        $result = $this->database->safeselect(
            ['id', 'title'],
            'member_groups',
            'WHERE `legend`=1 ORDER BY `title`',
        );
        while ($row = $this->database->arow($result)) {
            $legend .= '<a href="?" class="mgroup' . $row['id'] . '">'
                . $row['title'] . '</a> ';
        }

        return $page . $this->page->meta(
            'idx-stats',
            $usersonline[1],
            $usersonline[0],
            $usersonline[2],
            $nuserstoday,
            $userstoday,
            number_format($stats['members']),
            number_format($stats['topics']),
            number_format($stats['posts']),
            $this->page->meta(
                'user-link',
                $stats['last_register'],
                $stats['group_id'],
                $stats['display_name'],
            ),
            $legend,
        );
    }

    private function getUsersOnlineList(): array
    {
        $html = '';
        $guests = 0;
        $numMembers = 0;

        foreach ($this->database->getUsersOnline($this->user->isAdmin()) as $user) {
            if (
                $user['uid'] === null || $user['uid'] === 0
                || (bool) $user['is_bot']
            ) {
                ++$guests;

                continue;
            }

            $title = $this->textFormatting->blockhtml(
                $this->jax->pick($user['location_verbose'], 'Viewing the board.'),
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
                    . ($user['status'] === 'idle' ? ' idle' : '')
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
                ];
            }

            if (isset($oldcache)) {
                unset($oldcache[$user['uid']]);
            }

            $useronlinecache .= $user['uid'] . ',';
        }

        if (isset($oldcache) && $oldcache !== []) {
            $this->page->JS('setoffline', implode(',', array_flip($oldcache)));
        }

        $this->session->set('users_online_cache', mb_substr($useronlinecache, 0, -1));
        if ($list === []) {
            return;
        }

        $this->page->JS('onlinelist', $list);
    }

    private function updateLastPosts(): void
    {
        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT
                    f.`id` AS `id`,
                    f.`lp_tid` AS `lp_tid`,
                    f.`lp_topic` AS `lp_topic`,
                    UNIX_TIMESTAMP(f.`lp_date`) AS `lp_date`,
                    f.`lp_uid` AS `lp_uid`,
                    f.`topics` AS `topics`,
                    f.`posts` AS `posts`,
                    m.`display_name` AS `lp_name`,
                    m.`group_id` AS `lp_gid`
                FROM %t f
                LEFT JOIN %t m ON f.`lp_uid`=m.`id`
                WHERE f.`lp_date`>=?
                SQL
            ,
            ['forums', 'members'],
            $this->session->get('last_update') ? $this->database->datetime((int) $this->session->get('last_update')) : $this->database->datetime(),
        );

        while ($forum = $this->database->arow($result)) {
            $this->page->JS('addclass', '#fid_' . $forum['id'], 'unread');
            $this->page->JS(
                'update',
                '#fid_' . $forum['id'] . '_icon',
                $this->jax->pick(
                    $this->page->meta('icon-unread'),
                    $this->page->meta('idx-icon-unread'),
                ),
            );
            $this->page->JS(
                'update',
                '#fid_' . $forum['id'] . '_lastpost',
                $this->formatlastpost($forum),
                '1',
            );
            $this->page->JS(
                'update',
                '#fid_' . $forum['id'] . '_topics',
                $this->page->meta('idx-topics-count', $forum['topics']),
            );
            $this->page->JS(
                'update',
                '#fid_' . $forum['id'] . '_replies',
                $this->page->meta('idx-replies-count', $forum['posts']),
            );
        }
    }

    private function formatlastpost($forum): ?string
    {
        return $this->page->meta(
            'idx-row-lastpost',
            $forum['lp_tid'],
            $this->jax->pick(
                $this->textFormatting->wordfilter($forum['lp_topic']),
                '- - - - -',
            ),
            $forum['lp_uid'] ? $this->page->meta(
                'user-link',
                $forum['lp_uid'],
                $forum['lp_gid'],
                $forum['lp_name'],
            ) : 'None',
            $this->jax->pick($this->jax->date($forum['lp_date']), '- - - - -'),
        );
    }

    private function isForumRead($forum): bool
    {
        if (!$this->forumsread) {
            $this->forumsread = $this->jax->parsereadmarkers($this->session->get('forumsread'));
        }

        if (!isset($this->forumsread[$forum['id']])) {
            $this->forumsread[$forum['id']] = null;
        }

        return $forum['lp_date'] < max(
            $this->forumsread[$forum['id']],
            $this->session->get('read_date'),
            $this->user->get('last_visit'),
        );
    }
}
