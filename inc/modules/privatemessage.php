<?php

declare(strict_types=1);

new IM();
final class IM
{
    public function __construct()
    {
        global $JAX,$DB,$PAGE,$SESS;
        $im = $JAX->p['im_im'] ?? null;
        $uid = $JAX->p['im_uid'] ?? null;
        if ($SESS->runonce) {
            $this->filter();
        }

        if (trim($im ?? '') !== '' && $uid) {
            $this->message($uid, $im);
        }

        if (!isset($JAX->b['in_menu']) || !$JAX->b['im_menu']) {
            return;
        }

        $this->immenu($JAX->b['im_menu']);
    }

    public function filter(): void
    {
        global $SESS,$USER,$PAGE;
        if (!$USER['enemies']) {
            return;
        }

        $enemies = explode(',', (string) $USER['enemies']);
        // Kinda gross I know, unparses then parses then
        // unparses again later on.. Oh well.
        $exploded = explode(PHP_EOL, (string) $SESS->runonce);
        foreach ($exploded as $k => $v) {
            $v = json_decode($v);
            if ($v[0] !== 'im') {
                continue;
            }

            unset($exploded[$k]);
            if (in_array($v[1], $enemies)) {
                // This user's blocked, don't do anything.
            } else {
                // Send it on up.
                $PAGE->JSRawArray($v);
            }
        }

        $SESS->runonce = implode(PHP_EOL, $exploded);
    }

    public function message($uid, $im)
    {
        global $DB,$JAX,$PAGE,$SESS,$CFG,$USER,$PERMS;
        $SESS->act();
        $ud = $USER;
        $e = '';
        $fatal = false;

        if (!$ud) {
            return $PAGE->JS('error', 'You must be logged in to instant message!');
        }

        if (!$uid) {
            return $PAGE->JS('error', 'You must have a recipient!');
        }

        if (!$PERMS['can_im']) {
            return $PAGE->JS(
                'error',
                "You don't have permission to use this feature.",
            );
        }

        $im = $JAX->linkify($im);
        $im = $JAX->theworks($im);

        $cmd = [
            'im',
            $uid,
            $ud['display_name'],
            $im,
            $USER['id'],
            time(),
        ];
        $PAGE->JSRawArray($cmd);
        $cmd[1] = $ud['id'];
        $cmd[4] = 0;
        $onlineusers = $DB->getUsersOnline();
        $logoutTime = time() - $CFG['timetologout'];
        $updateTime = time() - $CFG['updateinterval'] * 5;
        if (
            !isset($onlineusers[$uid])
            || !$onlineusers[$uid]
            || $onlineusers[$uid]['last_update'] < $logoutTime
            || $onlineusers[$uid]['last_update'] < $updateTime
        ) {
            $PAGE->JS('imtoggleoffline', $uid);
        }

        if (!$this->sendcmd($cmd, $uid)) {
            $PAGE->JS('imtoggleoffline', $uid);
        }

        return !$e && !$fatal;
    }

    public function sendcmd($cmd, $uid): ?bool
    {
        global $DB,$CFG;
        if (!is_numeric($uid)) {
            return null;
        }

        $DB->safespecial(
            <<<'EOT'
                UPDATE %t
                SET `runonce`=CONCAT(`runonce`,?)
                WHERE `uid`=? AND `last_update`> ?
                EOT
            ,
            ['session'],
            $DB->basicvalue(json_encode($cmd) . PHP_EOL),
            $uid,
            date('Y-m-d H:i:s', time() - $CFG['updateinterval'] * 5),
        );

        return $DB->affected_rows(1) !== 0;
    }

    // Stuff I'm doing.
    public function invite($room, $uid, $otherguy = false): void
    {
        global $CFG, $DB, $PAGE, $USER;
        if (!$USER['id']) {
            return;
        }

        if ($otherguy) {
            $room = base64_encode(openssl_random_pseudo_bytes(128));
            // Make the window the guy that invited multi.
            $PAGE->JS('immakemulti', $otherguy);
            // Update other guy.
            $this->sendcmd(['immakemulti', $USER['id']], $otherguy);
        }

        $this->sendcmd(['iminvite', $room]);
    }

    public function immenu($id): void
    {
        global $PAGE,$JAX,$USER,$DB;
        if ($JAX->b['im_invitemenu']) {
            $online = $DB->getUsersOnline();
            $result = $DB->safeselect(
                [
                    'id',
                    '`display_name` AS `name`',
                ],
                'members',
                'WHERE `id` IN ? ORDER BY `name` ASC',
                explode(',', (string) $USER['friends']),
            );
            $menu = '';
            while ($f = $DB->arow($result)) {
                if (!$online[$f['id']]) {
                    continue;
                }

                if ($f['id'] === $id) {
                    continue;
                }

                $menu .= $f['name'] . '<br />';
            }

            if ($menu === '' || $menu === '0') {
                $menu = $USER['friends']
                    ? 'None of your friends<br />are currently online'
                    : 'You must add users to your contacts list<br />'
                                . 'to use this feature.';
            }
        } else {
            $menu = "<a href='?act=vu{$id}'>View Profile</a><br />"
                . "<a href='?module=privatemessage&im_menu={$id}"
                . "&im_invitemenu=1'>Add User to Chat</a>";
        }

        $PAGE->JS('update', 'immenu', $menu);
        $PAGE->JS('softurl');
    }
}
