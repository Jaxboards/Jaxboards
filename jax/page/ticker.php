<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\Jax;
use Jax\Page;
use Jax\Session;
use Jax\User;

final class Ticker
{
    private $maxticks = 60;

    public function __construct(
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Session $session,
        private readonly User $user,
    ) {
        $this->page->loadmeta('ticker');
    }

    public function route(): void
    {
        if ($this->page->jsnewlocation || !$this->page->jsaccess) {
            $this->index();
        } else {
            $this->update();
        }
    }

    public function index(): void
    {
        $this->session->set('location_verbose', 'Using the ticker!');
        $result = $this->database->safespecial(
            <<<'EOT'
                SELECT
                    f.`perms` AS `perms`,
                    f.`title` AS `ftitle`,
                    m.`display_name` AS `display_name`,
                    m.`group_id` AS `group_id`,
                    m2.`display_name` AS `display_name2`,
                    m2.`group_id` AS `group_id2`,
                    p.`auth_id` AS `auth_id`,
                    p.`id` AS `id`,
                    p.`tid` AS `tid`,
                    t.`auth_id` AS `auth_id2`,
                    t.`fid` AS `fid`,
                    t.`replies` AS `replies`,
                    t.`title` AS `title`,
                    UNIX_TIMESTAMP(p.`date`) AS `date`
                FROM %t p
                LEFT JOIN %t t
                    ON t.`id`=p.`tid`
                LEFT JOIN %t f
                    ON f.`id`=t.`fid`
                LEFT JOIN %t m
                    ON p.`auth_id`=m.`id`
                LEFT JOIN %t m2
                    ON t.`auth_id`=m2.`id`
                ORDER BY p.`id` DESC
                LIMIT ?
                EOT
            ,
            ['posts', 'topics', 'forums', 'members', 'members'],
            $this->maxticks,
        );
        $ticks = '';
        $first = 0;
        while ($tick = $this->database->arow($result)) {
            $p = $this->user->parseForumPerms($tick['perms']);
            if (!$p['read']) {
                continue;
            }

            if (!$first) {
                $first = $tick['id'];
            }

            $ticks .= $this->ftick($tick);
        }

        $this->session->addVar('tickid', $first);
        $page = $this->page->meta('ticker', $ticks);
        $this->page->append('PAGE', $page);
        $this->page->JS('update', 'page', $page);
    }

    public function update(): void
    {
        $result = $this->database->safespecial(
            <<<'EOT'
                SELECT
                    f.`perms` AS `perms`,
                    f.`title` AS `ftitle`,
                    m.`display_name` AS `display_name`,
                    m.`group_id` AS `group_id`,
                    m2.`display_name` AS `display_name2`,
                    m2.`group_id` AS `group_id2`,
                    p.`auth_id` AS `auth_id`,
                    p.`id` AS `id`,
                    p.`tid` AS `tid`,
                    t.`auth_id` AS `auth_id2`,
                    t.`fid` AS `fid`,
                    t.`replies` AS `replies`,
                    t.`title` AS `title`,
                    UNIX_TIMESTAMP(p.`date`) AS `date`
                FROM %t p
                LEFT JOIN %t t
                    ON t.`id`=p.`tid`
                LEFT JOIN %t f
                    ON f.`id`=t.`fid`
                LEFT JOIN %t m
                    ON p.`auth_id`=m.`id`
                LEFT JOIN %t m2
                    ON t.`auth_id`=m2.`id`
                WHERE p.`id` > ?
                ORDER BY p.`id` DESC
                LIMIT ?
                EOT
            ,
            ['posts', 'topics', 'forums', 'members', 'members'],
            $this->session->getVar('tickid') ?? 0,
            $this->maxticks,
        );
        $first = false;
        while ($f = $this->database->arow($result)) {
            $p = $this->user->parseForumPerms($f['perms']);
            if (!$p['read']) {
                continue;
            }

            if (!$first) {
                $first = $f['id'];
            }

            $this->page->JS('tick', $this->ftick($f));
        }

        if (!$first) {
            return;
        }

        $this->session->addVar('tickid', $first);
    }

    public function ftick($t): ?string
    {
        return $this->page->meta(
            'ticker-tick',
            $this->jax->smalldate($t['date'], false, true),
            $this->page->meta(
                'user-link',
                $t['auth_id'],
                $t['group_id'],
                $t['display_name'],
            ),
            $t['tid'],
            $t['id'],
            // Post id.
            $t['title'],
            $t['fid'],
            $t['ftitle'],
            $this->page->meta(
                'user-link',
                $t['auth_id2'],
                $t['group_id2'],
                $t['display_name2'],
            ),
            $t['replies'],
        );
    }
}
