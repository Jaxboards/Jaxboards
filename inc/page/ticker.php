<?php

declare(strict_types=1);

$PAGE->loadmeta('ticker');

final class Ticker
{
    public $maxticks = 60;

    public function route(): void
    {
        global $PAGE;
        if ($PAGE->jsnewlocation || !$PAGE->jsaccess) {
            $this->index();
        } else {
            $this->update();
        }
    }

    public function index(): void
    {
        global $PAGE,$DB,$SESS,$JAX,$USER;
        $SESS->location_verbose = 'Using the ticker!';
        $result = $DB->safespecial(
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
        while ($tick = $DB->arow($result)) {
            $p = $JAX->parseperms($tick['perms'], $USER ? $USER['group_id'] : 3);
            if (!$p['read']) {
                continue;
            }

            if (!$first) {
                $first = $tick['id'];
            }

            $ticks .= $this->ftick($tick);
        }

        $SESS->addvar('tickid', $first);
        $page = $PAGE->meta('ticker', $ticks);
        $PAGE->append('PAGE', $page);
        $PAGE->JS('update', 'page', $page);
    }

    public function update(): void
    {
        global $PAGE,$DB,$SESS,$USER,$JAX;
        $result = $DB->safespecial(
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
            $JAX->pick($SESS->vars['tickid'], 0),
            $this->maxticks,
        );
        $first = false;
        while ($f = $DB->arow($result)) {
            $p = $JAX->parseperms($f['perms'], $USER ? $USER['group_id'] : 3);
            if (!$p['read']) {
                continue;
            }

            if (!$first) {
                $first = $f['id'];
            }

            $PAGE->JS('tick', $this->ftick($f));
        }

        if (!$first) {
            return;
        }

        $SESS->addvar('tickid', $first);
    }

    public function ftick($t)
    {
        global $PAGE,$JAX;

        return $PAGE->meta(
            'ticker-tick',
            $JAX->smalldate($t['date'], false, true),
            $PAGE->meta(
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
            $PAGE->meta(
                'user-link',
                $t['auth_id2'],
                $t['group_id2'],
                $t['display_name2'],
            ),
            $t['replies'],
        );
    }
}
