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
            return;
        }

        $this->update();
    }

    private function index(): void
    {
        $this->session->set('location_verbose', 'Using the ticker!');


        $ticks = '';
        $first = 0;
        foreach ($this->fetchTicks() as $tick) {
            $perms = $this->user->getForumPerms($tick['perms']);
            if (!$perms['read']) {
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

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchTicks($lastTickId = null): array
    {
        $result = $this->database->safespecial(
            <<<"SQL"
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
                SQL,
            ['posts', 'topics', 'forums', 'members', 'members'],
            $lastTickId ?? 0,
            $this->maxticks,
        );
        return array_filter(
            $this->database->arows($result),

            // Filter out any ticks they don't have read access to
            fn($tick) => (bool) $this->user->getForumPerms($tick['perms'])['read']
        );
    }

    private function update(): void
    {
        $ticks = $this->fetchTicks($this->session->getVar('tickid'));

        if ($ticks === []) {
            return;
        }

        foreach ($ticks as $tick) {
            $this->page->command('tick', $this->renderTick($tick));
        }

        $this->session->addVar('tickid', $ticks[0]['id']);
    }

    /**
     * @param array<string,mixed> $tick
     */
    private function renderTick(array $tick): string
    {
        return $this->template->meta(
            'ticker-tick',
            $this->date->smallDate($tick['date'], ['autodate' => true]),
            $this->template->meta(
                'user-link',
                $tick['auth_id'],
                $tick['group_id'],
                $tick['display_name'],
            ),
            $tick['tid'],
            $tick['id'],
            // Post id.
            $tick['title'],
            $tick['fid'],
            $tick['ftitle'],
            $this->template->meta(
                'user-link',
                $tick['auth_id2'],
                $tick['group_id2'],
                $tick['display_name2'],
            ),
            $tick['replies'],
        );
    }
}
