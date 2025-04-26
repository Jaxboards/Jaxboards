<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\Page;
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
        private readonly Session $session,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {
        $this->page->loadmeta('idx');
    }

    public function render(): void
    {
        if (isset($this->jax->b['markread']) && $this->jax->b['markread']) {
            $this->page->JS('softurl');
            $this->session->set('forumsread', '{}');
            $this->session->set('topicsread', '{}');
            $this->session->set('read_date', time());
        }

        if ($this->page->jsupdate) {
            $this->update();
        } else {
            $this->viewidx();
        }
    }

    public function viewidx(): void
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

        if ($this->page->jsnewlocation) {
            $this->page->JS('update', 'page', $page);
            $this->page->updatepath();
        } else {
            $this->page->append('PAGE', $page);
        }
    }

    public function getsubs($forumId)
    {
        if (!isset($this->subforumids[$forumId]) || !$this->subforumids[$forumId]) {
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

    public function getmods($modids): string
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

    public function buildTable($a): ?string
    {
        if (!$a) {
            return null;
        }

        $forum = '';
        foreach ($a as $modId) {
            $read = $this->isForumRead($modId);
            $sf = '';
            if ($modId['show_sub'] >= 1 && isset($this->subforums[$modId['id']])) {
                $sf = $this->subforums[$modId['id']];
            }

            if ($modId['show_sub'] === 2) {
                foreach ($this->getsubs($modId['id']) as $i) {
                    $sf .= $this->subforums[$i];
                }
            }

            if ($modId['redirect']) {
                $forum .= $this->page->meta(
                    'idx-redirect-row',
                    $modId['id'],
                    $modId['title'],
                    nl2br((string) $modId['subtitle']),
                    'Redirects: ' . $modId['redirects'],
                    $this->jax->pick(
                        $this->page->meta('icon-redirect'),
                        $this->page->meta('idx-icon-redirect'),
                    ),
                );
            } else {
                $vId = $modId['id'];
                $hrefCode = $read
                    ? ''
                    : ' href="?act=vf' . $modId['id'] . '&amp;markread=1"';
                $linkText = $read
                    ? $this->jax->pick(
                        $this->page->meta('icon-read'),
                        $this->page->meta('idx-icon-read'),
                    ) : $this->jax->pick(
                        $this->page->meta('icon-unread'),
                        $this->page->meta('idx-icon-unread'),
                    );
                $forum .= $this->page->meta(
                    'idx-row',
                    $modId['id'],
                    $this->textFormatting->wordfilter($modId['title']),
                    nl2br((string) $modId['subtitle']),
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
                    $this->formatlastpost($modId),
                    $this->page->meta('idx-topics-count', $modId['topics']),
                    $this->page->meta('idx-replies-count', $modId['posts']),
                    $read ? 'read' : 'unread',
                    <<<EOT
                         <a id="fid_{$vId}_icon"{$hrefCode}>
                            {$linkText}
                        </a>
                        EOT
                    ,
                    $modId['show_ledby'] && $modId['mods']
                        ? $this->page->meta(
                            'idx-ledby-wrapper',
                            $this->getmods($modId['mods']),
                        ) : '',
                );
            }
        }

        return $this->page->meta('idx-table', $forum);
    }

    public function update(): void
    {
        $this->updateStats();
        $this->updateLastPosts();
    }

    public function getBoardStats(): string
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
        $usersonline = $this->getusersonlinelist();
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

    public function getusersonlinelist(): array
    {
        $forum = '';
        $guests = 0;
        $nummembers = 0;

        foreach ($this->database->getUsersOnline($this->user->isAdmin()) as $user) {
            if (
                !empty($user['uid'])
                || (
                    isset($user['is_bot'])
                    && $user['is_bot']
                )
            ) {
                $title = $this->textFormatting->blockhtml(
                    $this->jax->pick($user['location_verbose'], 'Viewing the board.'),
                );
                if (isset($user['is_bot']) && $user['is_bot']) {
                    $forum .= '<a class="user' . $user['uid'] . '" '
                        . 'title="' . $title . '" data-use-tooltip="true">'
                        . $user['name'] . '</a>';
                } else {
                    ++$nummembers;
                    $forum .= sprintf(
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
            } else {
                $guests = $user;
            }
        }

        return [$forum, $nummembers, $guests];
    }

    public function updateStats(): void
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

    public function updateLastPosts(): void
    {
        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT
                    user.`id` AS `id`,
                    user.`lp_tid` AS `lp_tid`,
                    user.`lp_topic` AS `lp_topic`,
                    UNIX_TIMESTAMP(user.`lp_date`) AS `lp_date`,
                    user.`lp_uid` AS `lp_uid`,
                    user.`topics` AS `topics`,
                    user.`posts` AS `posts`,
                    m.`display_name` AS `lp_name`,
                    m.`group_id` AS `lp_gid`
                FROM %t user
                LEFT JOIN %t m ON user.`lp_uid`=m.`id`
                WHERE user.`lp_date`>=?
                SQL
            ,
            ['forums', 'members'],
            $this->session->get('last_update') ? $this->database->datetime((int) $this->session->get('last_update')) : $this->database->datetime(),
        );

        while ($user = $this->database->arow($result)) {
            $this->page->JS('addclass', '#fid_' . $user['id'], 'unread');
            $this->page->JS(
                'update',
                '#fid_' . $user['id'] . '_icon',
                $this->jax->pick(
                    $this->page->meta('icon-unread'),
                    $this->page->meta('idx-icon-unread'),
                ),
            );
            $this->page->JS(
                'update',
                '#fid_' . $user['id'] . '_lastpost',
                $this->formatlastpost($user),
                '1',
            );
            $this->page->JS(
                'update',
                '#fid_' . $user['id'] . '_topics',
                $this->page->meta('idx-topics-count', $user['topics']),
            );
            $this->page->JS(
                'update',
                '#fid_' . $user['id'] . '_replies',
                $this->page->meta('idx-replies-count', $user['posts']),
            );
        }
    }

    public function formatlastpost($modId): ?string
    {
        return $this->page->meta(
            'idx-row-lastpost',
            $modId['lp_tid'],
            $this->jax->pick(
                $this->textFormatting->wordfilter($modId['lp_topic']),
                '- - - - -',
            ),
            $modId['lp_uid'] ? $this->page->meta(
                'user-link',
                $modId['lp_uid'],
                $modId['lp_gid'],
                $modId['lp_name'],
            ) : 'None',
            $this->jax->pick($this->jax->date($modId['lp_date']), '- - - - -'),
        );
    }

    public function isForumRead($forum): bool
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
