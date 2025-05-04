<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Database;
use Jax\Date;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\Template;
use Jax\User;

final class Ticker
{
    private int $maxticks = 60;

    public function __construct(
        private readonly Database $database,
        private readonly Date $date,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Session $session,
        private readonly Template $template,
        private readonly User $user,
    ) {
        $this->template->loadMeta('ticker');
    }

    public function render(): void
    {
        if (
            $this->request->isJSNewLocation()
            || !$this->request->isJSAccess()
        ) {
            $this->index();
        } else {
            $this->update();
        }
    }

    private function index(): void
    {
        $this->session->set('location_verbose', 'Using the ticker!');
        $result = $this->database->safespecial(
            <<<'SQL'
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
                SQL
            ,
            ['posts', 'topics', 'forums', 'members', 'members'],
            $this->maxticks,
        );
        $ticks = '';
        $first = 0;
        while ($tick = $this->database->arow($result)) {
            $p = $this->user->getForumPerms($tick['perms']);
            if (!$p['read']) {
                continue;
            }

            if (!$first) {
                $first = $tick['id'];
            }

            $ticks .= $this->renderTick($tick);
        }

        $this->session->addVar('tickid', $first);
        $page = $this->template->meta('ticker', $ticks);
        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
    }

    private function update(): void
    {
        $result = $this->database->safespecial(
            <<<'SQL'
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
                SQL
            ,
            ['posts', 'topics', 'forums', 'members', 'members'],
            $this->session->getVar('tickid') ?? 0,
            $this->maxticks,
        );
        $first = false;
        while ($f = $this->database->arow($result)) {
            $p = $this->user->getForumPerms($f['perms']);
            if (!$p['read']) {
                continue;
            }

            if (!$first) {
                $first = $f['id'];
            }

            $this->page->command('tick', $this->renderTick($f));
        }

        if (!$first) {
            return;
        }

        $this->session->addVar('tickid', $first);
    }

    private function renderTick(array $t): ?string
    {
        return $this->template->meta(
            'ticker-tick',
            $this->date->smallDate($t['date'], ['autodate' => true]),
            $this->template->meta(
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
            $this->template->meta(
                'user-link',
                $t['auth_id2'],
                $t['group_id2'],
                $t['display_name2'],
            ),
            $t['replies'],
        );
    }
}
