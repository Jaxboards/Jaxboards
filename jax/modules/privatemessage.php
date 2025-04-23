<?php

declare(strict_types=1);

namespace Jax\Modules;

use Jax\Config;

final readonly class PrivateMessage
{
    public function __construct(private Config $config) {}

    public function init(): void
    {
        global $JAX,$DB,$PAGE,$SESS;
        $im = $JAX->p['im_im'] ?? null;
        $uid = $JAX->p['im_uid'] ?? null;
        if ($SESS->runonce) {
            $this->filter();
        }

        if (trim($im ?? '') === '' || !$uid) {
            return;
        }

        $this->message($uid, $im);
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
        global $DB,$JAX,$PAGE,$SESS,$USER,$PERMS;
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
        $logoutTime = time() - $this->config->getSetting('timetologout');
        $updateTime = time() - $this->config->getSetting('updateinterval') * 5;
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
        global $DB;
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
            gmdate('Y-m-d H:i:s', time() - $this->config->getSetting('updateinterval') * 5),
        );

        return $DB->affected_rows(1) !== 0;
    }
}
