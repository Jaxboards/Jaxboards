<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\Page;

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

final class IDX
{
    public $moderatorinfo;

    public $forumsread = [];

    public $mods;

    public $subforumids;

    public $subforums;

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
    ) {
        $this->page->loadmeta('idx');
    }

    public function route(): void
    {
        global $SESS;
        if (isset($this->jax->b['markread']) && $this->jax->b['markread']) {
            $this->page->JS('softurl');
            $SESS->forumsread = '{}';
            $SESS->topicsread = '{}';
            $SESS->read_date = time();
        }

        if ($this->page->jsupdate) {
            $this->update();
        } else {
            $this->viewidx();
        }
    }

    public function viewidx(): void
    {
        global $SESS,$USER;
        $SESS->location_verbose = 'Viewing board index';
        $page = '';
        $result = $this->database->safespecial(
            <<<'EOT'
                SELECT f.`id` AS `id`,
                    f.`cat_id` AS `cat_id`,f.`title` AS `title`,f.`subtitle` AS `subtitle`,
                    f.`lp_uid` AS `lp_uid`,UNIX_TIMESTAMP(f.`lp_date`) AS `lp_date`,
                    f.`lp_tid` AS `lp_tid`,f.`lp_topic` AS `lp_topic`,f.`path` AS `path`,
                    f.`show_sub` AS `show_sub`,f.`redirect` AS `redirect`,
                    f.`topics` AS `topics`,f.`posts` AS `posts`,f.`order` AS `order`,
                    f.`perms` AS `perms`,f.`orderby` AS `orderby`,f.`nocount` AS `nocount`,
                    f.`redirects` AS `redirects`,f.`trashcan` AS `trashcan`,f.`mods` AS `mods`,
                    f.`show_ledby` AS `show_ledby`,m.`display_name` AS `lp_name`,
                    m.`group_id` AS `lp_gid`
                FROM %t f
                LEFT JOIN %t m
                    ON f.`lp_uid`=m.`id`
                ORDER BY f.`order`, f.`title` ASC
                EOT
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
        while ($r = $this->database->arow($result)) {
            $perms = $this->jax->parseperms($r['perms'], $USER ? $USER['group_id'] : 3);
            if ($r['perms'] && !$perms['view']) {
                continue;
            }

            // Store subforum details for later.
            if ($r['path']) {
                preg_match('@\d+$@', (string) $r['path'], $m);
                if (isset($this->subforums[$m[0]])) {
                    $this->subforumids[$m[0]][] = $r['id'];
                    $this->subforums[$m[0]] .= $this->page->meta(
                        'idx-subforum-link',
                        $r['id'],
                        $r['title'],
                        $this->jax->blockhtml($r['subtitle']),
                    ) . $this->page->meta('idx-subforum-splitter');
                }
            } else {
                $data[$r['cat_id']][] = $r;
            }

            // Store mod details for later.
            if (!$r['show_ledby']) {
                continue;
            }

            if (!$r['mods']) {
                continue;
            }

            foreach (explode(',', (string) $r['mods']) as $v) {
                if ($v === '') {
                    continue;
                }

                if ($v === '0') {
                    continue;
                }

                $this->mods[$v] = 1;
            }
        }

        $this->mods = array_keys($this->mods);
        $catq = $this->database->safeselect(
            ['id', 'title', '`order`'],
            'categories',
            'ORDER BY `order`,`title` ASC',
        );
        while ($r = $this->database->arow($catq)) {
            if (empty($data[$r['id']])) {
                continue;
            }

            $page .= $this->page->collapsebox(
                $r['title'],
                $this->buildTable(
                    $data[$r['id']],
                ),
                'cat_' . $r['id'],
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

    public function getsubs($id)
    {
        if (!isset($this->subforumids[$id]) || !$this->subforumids[$id]) {
            return [];
        }

        $r = $this->subforumids[$id];
        foreach ($r as $v) {
            if (!$this->subforumids[$v]) {
                continue;
            }

            $r = array_merge($r, $this->getsubs($v));
        }

        return $r;
    }

    public function getmods($modids): string
    {
        if (!$this->moderatorinfo) {
            $this->moderatorinfo = [];
            $result = $this->database->safeselect(
                ['id', 'display_name', 'group_id'],
                'members',
                'WHERE `id` IN ?',
                $this->mods,
            );
            while ($member = $this->database->arow($result)) {
                $this->moderatorinfo[$member['id']] = $this->page->meta(
                    'user-link',
                    $member['id'],
                    $member['group_id'],
                    $member['display_name'],
                );
            }
        }

        $r = '';
        foreach (explode(',', (string) $modids) as $v) {
            $r .= $this->moderatorinfo[$v] . $this->page->meta('idx-ledby-splitter');
        }

        return mb_substr($r, 0, -mb_strlen((string) $this->page->meta('idx-ledby-splitter')));
    }

    public function buildTable($a): ?string
    {
        if (!$a) {
            return null;
        }

        $r = '';
        foreach ($a as $v) {
            $read = $this->isForumRead($v);
            $sf = '';
            if ($v['show_sub'] >= 1 && isset($this->subforums[$v['id']])) {
                $sf = $this->subforums[$v['id']];
            }

            if ($v['show_sub'] === 2) {
                foreach ($this->getsubs($v['id']) as $i) {
                    $sf .= $this->subforums[$i];
                }
            }

            if ($v['redirect']) {
                $r .= $this->page->meta(
                    'idx-redirect-row',
                    $v['id'],
                    $v['title'],
                    nl2br((string) $v['subtitle']),
                    'Redirects: ' . $v['redirects'],
                    $this->jax->pick(
                        $this->page->meta('icon-redirect'),
                        $this->page->meta('idx-icon-redirect'),
                    ),
                );
            } else {
                $vId = $v['id'];
                $hrefCode = $read
                    ? ''
                    : ' href="?act=vf' . $v['id'] . '&amp;markread=1"';
                $linkText = $read
                    ? $this->jax->pick(
                        $this->page->meta('icon-read'),
                        $this->page->meta('idx-icon-read'),
                    ) : $this->jax->pick(
                        $this->page->meta('icon-unread'),
                        $this->page->meta('idx-icon-unread'),
                    );
                $r .= $this->page->meta(
                    'idx-row',
                    $v['id'],
                    $this->jax->wordfilter($v['title']),
                    nl2br((string) $v['subtitle']),
                    $sf
                    ? $this->page->meta(
                        'idx-subforum-wrapper',
                        mb_substr(
                            (string) $sf,
                            0,
                            -1 * mb_strlen(
                                (string) $this->page->meta('idx-subforum-splitter'),
                            ),
                        ),
                    ) : '',
                    $this->formatlastpost($v),
                    $this->page->meta('idx-topics-count', $v['topics']),
                    $this->page->meta('idx-replies-count', $v['posts']),
                    $read ? 'read' : 'unread',
                    <<<EOT
                         <a id="fid_{$vId}_icon"{$hrefCode}>
                            {$linkText}
                        </a>
                        EOT
                    ,
                    $v['show_ledby'] && $v['mods']
                        ? $this->page->meta(
                            'idx-ledby-wrapper',
                            $this->getmods($v['mods']),
                        ) : '',
                );
            }
        }

        return $this->page->meta('idx-table', $r);
    }

    public function update(): void
    {
        $this->updateStats();
        $this->updateLastPosts();
    }

    public function getBoardStats(): string
    {
        global $PERMS;
        if (!$PERMS['can_view_stats']) {
            return '';
        }

        $legend = '';
        $page = '';
        $userstoday = '';
        $result = $this->database->safespecial(
            <<<'EOT'
                SELECT s.`posts` AS `posts`,s.`topics` AS `topics`,s.`members` AS `members`,
                    s.`most_members` AS `most_members`,
                    s.`most_members_day` AS `most_members_day`,
                    s.`last_register` AS `last_register`,m.`group_id` AS `group_id`,
                    m.`display_name` AS `display_name`
                FROM %t s
                LEFT JOIN %t m
                ON s.`last_register`=m.`id`
                EOT
            ,
            ['stats', 'members'],
        );
        $stats = $this->database->arow($result);
        $this->database->disposeresult($result);

        $result = $this->database->safespecial(
            <<<'EOT'
                SELECT UNIX_TIMESTAMP(MAX(s.`last_update`)) AS `last_update`,m.`id` AS `id`,
                    m.`group_id` AS `group_id`,m.`display_name` AS `name`,
                    CONCAT(MONTH(m.`birthdate`),' ',DAY(m.`birthdate`)) AS `birthday`,
                    UNIX_TIMESTAMP(MAX(s.`read_date`)) AS `read_date`,s.`hide` AS `hide`
                FROM %t s
                LEFT JOIN %t m
                    ON s.`uid`=m.`id`
                WHERE s.`uid` AND s.`hide` = 0
                GROUP BY m.`id`
                ORDER BY `name`
                EOT
            ,
            ['session', 'members'],
        );
        $nuserstoday = 0;
        $today = gmdate('n j');
        while ($f = $this->database->arow($result)) {
            if (!$f['id']) {
                continue;
            }

            $fId = $f['id'];
            $fName = $f['name'];
            $fGroupId = $f['group_id'];
            $birthdayCode = $f['birthday'] === $today
                && $this->config->getSetting('birthdays') ? ' birthday' : '';
            $lastOnlineCode = $this->jax->date(
                $f['hide'] ? $f['read_date'] : $f['last_update'],
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
        $r = '';
        $guests = 0;
        $nummembers = 0;
        foreach ($this->database->getUsersOnline() as $f) {
            if (!empty($f['uid']) || (isset($f['is_bot']) && $f['is_bot'])) {
                $title = $this->jax->blockhtml(
                    $this->jax->pick($f['location_verbose'], 'Viewing the board.'),
                );
                if (isset($f['is_bot']) && $f['is_bot']) {
                    $r .= '<a class="user' . $f['uid'] . '" '
                        . 'title="' . $title . '" data-use-tooltip="true">'
                        . $f['name'] . '</a>';
                } else {
                    ++$nummembers;
                    $r .= sprintf(
                        '<a href="?act=vu%1$s" class="user%1$s mgroup%2$s" '
                            . 'title="%4$s" data-use-tooltip="true">'
                            . '%3$s</a>',
                        $f['uid'],
                        $f['group_id']
                        . ($f['status'] === 'idle' ? ' idle' : '')
                        . ($f['birthday'] && $this->config->getSetting('birthdays') ? ' birthday' : ''),
                        $f['name'],
                        $title,
                    );
                }
            } else {
                $guests = $f;
            }
        }

        return [$r, $nummembers, $guests];
    }

    public function updateStats(): void
    {
        global $SESS;
        $list = [];
        if ($SESS->users_online_cache) {
            $oldcache = array_flip(explode(',', (string) $SESS->users_online_cache));
        }

        $useronlinecache = '';
        foreach ($this->database->getUsersOnline() as $f) {
            $lastActionIdle = (int) ($SESS->last_update ?? 0) - ($this->config->getSetting('timetoidle') ?? 300) - 30;
            if (!$f['uid'] && !$f['is_bot']) {
                continue;
            }

            if (
                $f['last_action'] >= (int) $SESS->last_update
                || $f['status'] === 'idle'
                && $f['last_action'] > $lastActionIdle
            ) {
                $list[] = [
                    $f['uid'],
                    $f['group_id'],

                    $f['status'] !== 'active'
                    ? $f['status']
                    : ($f['birthday'] && ($this->config->getSetting('birthdays') & 1)
                    ? ' birthday' : ''),
                    $f['name'],
                    $f['location_verbose'],
                ];
            }

            if (isset($oldcache)) {
                unset($oldcache[$f['uid']]);
            }

            $useronlinecache .= $f['uid'] . ',';
        }

        if (isset($oldcache) && $oldcache !== []) {
            $this->page->JS('setoffline', implode(',', array_flip($oldcache)));
        }

        $SESS->users_online_cache = mb_substr($useronlinecache, 0, -1);
        if ($list === []) {
            return;
        }

        $this->page->JS('onlinelist', $list);
    }

    public function updateLastPosts(): void
    {
        global $SESS;
        $result = $this->database->safespecial(
            <<<'EOT'
                SELECT f.`id` AS `id`,f.`lp_tid` AS `lp_tid`,f.`lp_topic` AS `lp_topic`,
                    UNIX_TIMESTAMP(f.`lp_date`) AS `lp_date`,f.`lp_uid` AS `lp_uid`,
                    f.`topics` AS `topics`,f.`posts` AS `posts`,m.`display_name` AS `lp_name`,
                    m.`group_id` AS `lp_gid`
                FROM %t f
                LEFT JOIN %t m
                    ON f.`lp_uid`=m.`id`
                WHERE f.`lp_date`>=?
                EOT
            ,
            ['forums', 'members'],
            gmdate('Y-m-d H:i:s', (int) ($SESS->last_update ?? time())),
        );

        while ($f = $this->database->arow($result)) {
            $this->page->JS('addclass', '#fid_' . $f['id'], 'unread');
            $this->page->JS(
                'update',
                '#fid_' . $f['id'] . '_icon',
                $this->jax->pick(
                    $this->page->meta('icon-unread'),
                    $this->page->meta('idx-icon-unread'),
                ),
            );
            $this->page->JS(
                'update',
                '#fid_' . $f['id'] . '_lastpost',
                $this->formatlastpost($f),
                '1',
            );
            $this->page->JS(
                'update',
                '#fid_' . $f['id'] . '_topics',
                $this->page->meta('idx-topics-count', $f['topics']),
            );
            $this->page->JS(
                'update',
                '#fid_' . $f['id'] . '_replies',
                $this->page->meta('idx-replies-count', $f['posts']),
            );
        }
    }

    public function formatlastpost($v): ?string
    {
        return $this->page->meta(
            'idx-row-lastpost',
            $v['lp_tid'],
            $this->jax->pick(
                $this->jax->wordfilter($v['lp_topic']),
                '- - - - -',
            ),
            $v['lp_uid'] ? $this->page->meta(
                'user-link',
                $v['lp_uid'],
                $v['lp_gid'],
                $v['lp_name'],
            ) : 'None',
            $this->jax->pick($this->jax->date($v['lp_date']), '- - - - -'),
        );
    }

    public function isForumRead($forum): bool
    {
        global $SESS,$USER;
        if (!$this->forumsread) {
            $this->forumsread = $this->jax->parsereadmarkers($SESS->forumsread);
        }

        if (!isset($this->forumsread[$forum['id']])) {
            $this->forumsread[$forum['id']] = null;
        }

        return $forum['lp_date'] < max(
            $this->forumsread[$forum['id']],
            $SESS->read_date,
            $USER && $USER['last_visit'],
        );
    }
}
