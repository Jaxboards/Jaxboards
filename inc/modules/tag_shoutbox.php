<?php

declare(strict_types=1);

$PAGE->loadmeta('shoutbox');

final class Shoutbox
{
    public $shoutlimit;

    public function init(): void
    {
        global $PAGE,$JAX,$CFG,$PERMS;
        if (!isset($CFG['shoutbox'])) {
            $CFG['shoutbox'] = false;
        }

        if (!isset($PERMS['can_view_shoutbox'])) {
            $PERMS['can_view_shoutbox'] = false;
        }

        if (!$CFG['shoutbox'] || !$PERMS['can_view_shoutbox']) {
            return;
        }

        $this->shoutlimit = $CFG['shoutbox_num'];
        if (
            isset($JAX->b['shoutbox_delete'])
            && is_numeric($JAX->b['shoutbox_delete'])
        ) {
            $this->deleteshout();
        } elseif (
            isset($JAX->b['module'])
            && $JAX->b['module'] === 'shoutbox'
        ) {
            $this->showallshouts();
        }

        if (
            isset($JAX->p['shoutbox_shout'])
            && trim($JAX->p['shoutbox_shout']) !== ''
        ) {
            $this->addshout();
        }

        if (!$PAGE->jsaccess) {
            $this->displayshoutbox();
        } else {
            $this->updateshoutbox();
        }
    }

    public function canDelete($id, $shoutrow = false)
    {
        global $PERMS,$USER,$DB;
        $candelete = $PERMS['can_delete_shouts'];
        if (!$candelete && $PERMS['can_delete_own_shouts']) {
            if (!$shoutrow) {
                $result = $DB->safeselect(
                    '`uid`',
                    'shouts',
                    'WHERE `id`=?',
                    $id,
                );
                $shoutrow = $DB->arow($result);
            }

            if (
                isset($shoutrow['uid'])
                && $shoutrow['uid'] === $USER['id']
            ) {
                $candelete = true;
            }
        }

        return $candelete;
    }

    public function formatshout($row)
    {
        global $PAGE,$JAX,$CFG;
        $shout = $JAX->theworks($row['shout'], ['minimalbb' => true]);
        $user = $row['uid'] ? $PAGE->meta(
            'user-link',
            $row['uid'],
            $row['group_id'],
            $row['display_name'],
        ) : 'Guest';
        $avatar = isset($CFG['shoutboxava']) && $CFG['shoutboxava']
            ? '<img src="' . $JAX->pick(
                $row['avatar'],
                $PAGE->meta('default-avatar'),
            ) . '" class="avatar" alt="avatar" />' : '';
        $deletelink = $PAGE->meta('shout-delete', $row['id']);
        if (!$this->canDelete(0, $row)) {
            $deletelink = '';
        }

        if (mb_substr((string) $shout, 0, 4) === '/me ') {
            return $PAGE->meta(
                'shout-action',
                $JAX->smalldate(
                    $row['date'],
                    1,
                ),
                $user,
                mb_substr(
                    (string) $shout,
                    3,
                ),
                $deletelink,
            );
        }

        return $PAGE->meta(
            'shout',
            $row['date'],
            $user,
            $shout . PHP_EOL,
            $deletelink,
            $avatar,
        );
    }

    public function displayshoutbox(): void
    {
        global $PAGE,$DB,$SESS,$USER;
        $result = $DB->safespecial(
            <<<'EOT'
                SELECT
                    m.`avatar` AS `avatar`,
                    m.`display_name` AS `display_name`,
                    m.`group_id` AS `group_id`,
                    s.`id` AS `id`,
                    s.`shout` AS `shout`,
                    s.`uid` AS `uid`,
                    UNIX_TIMESTAMP(s.`date`) AS `date`
                FROM %t s
                LEFT JOIN %t m
                    ON s.`uid`=m.`id`
                ORDER BY s.`id` DESC LIMIT ?
                EOT
            ,
            ['shouts', 'members'],
            $this->shoutlimit,
        );
        $shouts = '';
        $first = 0;
        while ($f = $DB->arow($result)) {
            if (!$first) {
                $first = $f['id'];
            }

            $shouts .= $this->formatshout($f);
        }

        $SESS->addvar('sb_id', $first);
        $PAGE->append(
            'shoutbox',
            $PAGE->meta(
                'collapsebox',
                " id='shoutbox'",
                $PAGE->meta(
                    'shoutbox-title',
                ),
                $PAGE->meta(
                    'shoutbox',
                    $shouts,
                ),
            ) . "<script type='text/javascript'>globalsettings.shoutlimit="
            . $this->shoutlimit . ';globalsettings.sound_shout='
            . (!$USER || $USER['sound_shout'] ? 1 : 0)
            . '</script>',
        );
    }

    public function updateshoutbox(): void
    {
        global $PAGE,$JAX,$DB,$SESS,$USER,$CFG;

        // This is a bit tricky, we're transversing the shouts
        // in reverse order, since they're shifted onto the list, not pushed.
        $last = 0;
        if (isset($SESS->vars['sb_id']) && $SESS->vars['sb_id']) {
            $result = $DB->safespecial(
                <<<'EOT'
                    SELECT
                        m.`avatar` AS `avatar`,
                        m.`display_name` AS `display_name`,
                        m.`group_id` AS `group_id`,
                        s.`id` AS `id`,
                        s.`shout` AS `shout`,
                        s.`uid` AS `uid`,
                        UNIX_TIMESTAMP(s.`date`) AS `date`
                    FROM %t s
                    LEFT JOIN %t m
                        ON s.`uid`=m.`id`
                    WHERE s.`id`>?
                    ORDER BY s.`id` ASC LIMIT ?
                    EOT
                ,
                ['shouts', 'members'],
                $JAX->pick($SESS->vars['sb_id'], 0),
                $this->shoutlimit,
            );
            while ($f = $DB->arow($result)) {
                $PAGE->JS('addshout', $this->formatshout($f));
                if (isset($CFG['shoutboxsounds']) && $CFG['shoutboxsounds']) {
                    $sounds = [];
                    if ($USER['sound_shout'] && $sounds[$f['shout']]) {
                        $PAGE->JS(
                            'playsound',
                            'sfx',
                            SOUNDSURL . $sounds[$f['shout']] . '.mp3',
                        );
                    }
                }

                $last = $f['id'];
            }
        }

        // Update the sb_id variable if we selected shouts.
        if (!$last) {
            return;
        }

        $SESS->addvar('sb_id', $last);
    }

    public function showallshouts(): void
    {
        global $PAGE,$DB,$JAX;
        $perpage = 100;
        $pagen = 0;
        $pages = '';
        $page = '';
        if (
            isset($JAX->b['page'])
            && is_numeric($JAX->b['page'])
            && $JAX->b['page'] > 1
        ) {
            $pagen = $JAX->b['page'] - 1;
        }

        $result = $DB->safeselect(
            'COUNT(`id`)',
            'shouts',
        );
        $thisrow = $DB->arow($result);
        $numshouts = array_pop($thisrow);
        $DB->disposeresult($result);
        if ($numshouts > 1000) {
            $numshouts = 1000;
        }

        if ($numshouts > $perpage) {
            $pages .= " &middot; Pages: <span class='pages'>";
            $pageArray = $JAX->pages(
                ceil($numshouts / $perpage),
                $pagen + 1,
                10,
            );
            foreach ($pageArray as $v) {
                $pages .= '<a href="?module=shoutbox&page='
                    . $v . '"'
                    . ($v + 1 === $pagen ? ' class="active"' : '')
                    . '>' . $v . '</a> ';
            }

            $pages .= '</span>';
        }

        $PAGE->path(['Shoutbox History' => '?module=shoutbox']);
        $PAGE->updatepath();
        if ($PAGE->jsupdate) {
            return;
        }

        $result = $DB->safespecial(
            <<<'EOT'
                SELECT
                    s.`id` AS `id`,
                    s.`uid` AS `uid`,
                    s.`shout` AS `shout`,
                    UNIX_TIMESTAMP(s.`date`) AS `date`,
                    m.`display_name` AS `display_name`,
                     m.`group_id` AS `group_id`,
                    m.`avatar` AS `avatar`
                FROM %t s
                LEFT JOIN %t m
                ON s.`uid`=m.`id`
                ORDER BY s.`id` DESC LIMIT ?,?
                EOT
            ,
            ['shouts', 'members'],
            $pagen * $perpage,
            $perpage,
        );
        $shouts = '';
        while ($f = $DB->arow($result)) {
            $shouts .= $this->formatshout($f);
        }

        $page = $PAGE->meta(
            'box',
            '',
            'Shoutbox' . $pages,
            '<div class="sbhistory">' . $shouts . '</div>',
        );
        $PAGE->JS('update', 'page', $page);
        $PAGE->append('PAGE', $page);
    }

    public function deleteshout()
    {
        global $JAX,$DB,$PAGE,$USER;
        if (!$USER) {
            return $PAGE->location('?');
        }

        $delete = $JAX->b['shoutbox_delete'] ?? 0;
        $candelete = $this->canDelete($delete);
        if (!$candelete) {
            return $PAGE->location('?');
        }

        $PAGE->JS('softurl');
        $DB->safedelete(
            'shouts',
            'WHERE `id`=?',
            $delete,
        );

        return null;
    }

    public function addshout(): void
    {
        global $JAX,$DB,$PAGE,$SESS;
        $SESS->act();
        $e = '';
        $shout = $JAX->p['shoutbox_shout'];
        $shout = $JAX->linkify($shout);

        $perms = $JAX->getPerms();
        if (!$perms['can_shout']) {
            $e = 'You do not have permission to shout!';
        } elseif (mb_strlen((string) $shout) > 300) {
            $e = 'Shout must be less than 300 characters.';
        }

        if ($e !== '' && $e !== '0') {
            $PAGE->JS('error', $e);
            $PAGE->prepend('shoutbox', $PAGE->error($e));

            return;
        }

        $DB->safeinsert(
            'shouts',
            [
                'date' => gmdate('Y-m-d H:i:s'),
                'ip' => $JAX->ip2bin(),
                'shout' => $shout,
                'uid' => $JAX->pick($JAX->userData['id'], 0),
            ],
        );
    }
}
