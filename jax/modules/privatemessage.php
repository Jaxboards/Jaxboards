<?php

declare(strict_types=1);

namespace Jax\Modules;

use Jax\Config;
use Jax\Jax;
use Jax\Page;
use Jax\Session;

final readonly class PrivateMessage
{
    public function __construct(
        private Config $config,
        private Jax $jax,
        private Page $page,
        private Session $session,
    ) {}

    public function init(): void
    {
        $im = $this->jax->p['im_im'] ?? null;
        $uid = $this->jax->p['im_uid'] ?? null;
        if ($this->session->runonce) {
            $this->filter();
        }

        if (trim($im ?? '') === '' || !$uid) {
            return;
        }

        $this->message($uid, $im);
    }

    public function filter(): void
    {
        global $USER;
        if (!$USER['enemies']) {
            return;
        }

        $enemies = explode(',', (string) $USER['enemies']);
        // Kinda gross I know, unparses then parses then
        // unparses again later on.. Oh well.
        $exploded = explode(PHP_EOL, (string) $this->session->runonce);
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
                $this->page->JSRawArray($v);
            }
        }

        $this->session->runonce = implode(PHP_EOL, $exploded);
    }

    public function message($uid, $im)
    {
        global $USER,$PERMS;
        $this->session->act();
        $ud = $USER;
        $e = '';
        $fatal = false;

        if (!$ud) {
            return $this->page->JS('error', 'You must be logged in to instant message!');
        }

        if (!$uid) {
            return $this->page->JS('error', 'You must have a recipient!');
        }

        if (!$PERMS['can_im']) {
            return $this->page->JS(
                'error',
                "You don't have permission to use this feature.",
            );
        }

        $im = $this->jax->linkify($im);
        $im = $this->jax->theworks($im);

        $cmd = [
            'im',
            $uid,
            $ud['display_name'],
            $im,
            $USER['id'],
            time(),
        ];
        $this->page->JSRawArray($cmd);
        $cmd[1] = $ud['id'];
        $cmd[4] = 0;
        $onlineusers = $this->database->getUsersOnline();
        $logoutTime = time() - $this->config->getSetting('timetologout');
        $updateTime = time() - $this->config->getSetting('updateinterval') * 5;
        if (
            !isset($onlineusers[$uid])
            || !$onlineusers[$uid]
            || $onlineusers[$uid]['last_update'] < $logoutTime
            || $onlineusers[$uid]['last_update'] < $updateTime
        ) {
            $this->page->JS('imtoggleoffline', $uid);
        }

        if (!$this->sendcmd($cmd, $uid)) {
            $this->page->JS('imtoggleoffline', $uid);
        }

        return !$e && !$fatal;
    }

    public function sendcmd($cmd, $uid): ?bool
    {
        if (!is_numeric($uid)) {
            return null;
        }

        $this->database->safespecial(
            <<<'EOT'
                UPDATE %t
                SET `runonce`=CONCAT(`runonce`,?)
                WHERE `uid`=? AND `last_update`> ?
                EOT
            ,
            ['session'],
            $this->database->basicvalue(json_encode($cmd) . PHP_EOL),
            $uid,
            gmdate('Y-m-d H:i:s', time() - $this->config->getSetting('updateinterval') * 5),
        );

        return $this->database->affected_rows(1) !== 0;
    }
}
