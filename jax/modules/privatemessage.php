<?php

declare(strict_types=1);

namespace Jax\Modules;

use Jax\Config;
use Jax\Database;
use Jax\Jax;
use Jax\Page;
use Jax\Session;
use Jax\TextFormatting;
use Jax\User;

use function explode;
use function implode;
use function in_array;
use function is_numeric;
use function json_decode;
use function json_encode;
use function time;
use function trim;

use const PHP_EOL;

/**
 * @psalm-api
 */
final readonly class PrivateMessage
{
    public function __construct(
        private Config $config,
        private Database $database,
        private Jax $jax,
        private Page $page,
        private Session $session,
        private TextFormatting $textFormatting,
        private User $user,
    ) {}

    public function init(): void
    {
        $instantMessage = $this->jax->p['im_im'] ?? null;
        $uid = $this->jax->p['im_uid'] ?? null;
        if ($this->session->get('runonce')) {
            $this->filter();
        }

        if (trim($instantMessage ?? '') === '' || !$uid) {
            return;
        }

        $this->message($uid, $instantMessage);
    }

    public function filter(): void
    {
        $enemies = $this->user->get('enemies');
        if (!$enemies) {
            return;
        }

        $enemies = explode(',', (string) $enemies);
        // Kinda gross I know, unparses then parses then
        // unparses again later on.. Oh well.
        $exploded = explode(PHP_EOL, (string) $this->session->get('runonce'));
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

        $this->session->set('runonce', implode(PHP_EOL, $exploded));
    }

    public function message($uid, $instantMessage)
    {
        $this->session->act();
        $error = null;
        $fatal = false;

        if ($this->user->isGuest()) {
            return $this->page->JS('error', 'You must be logged in to instant message!');
        }

        if (!$uid) {
            return $this->page->JS('error', 'You must have a recipient!');
        }

        if (!$this->user->getPerm('can_im')) {
            return $this->page->JS(
                'error',
                "You don't have permission to use this feature.",
            );
        }

        $instantMessage = $this->textFormatting->linkify($instantMessage);
        $instantMessage = $this->textFormatting->theworks($instantMessage);

        $cmd = [
            'im',
            $uid,
            $this->user->get('display_name'),
            $instantMessage,
            $this->user->get('id'),
            time(),
        ];
        $this->page->JSRawArray($cmd);
        $cmd[1] = $this->user->get('id');
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

        return !$error && !$fatal;
    }

    public function sendcmd($cmd, $uid): ?bool
    {
        if (!is_numeric($uid)) {
            return null;
        }

        $this->database->safespecial(
            <<<'SQL'
                UPDATE %t
                SET `runonce`=CONCAT(`runonce`,?)
                WHERE `uid`=? AND `last_update`> ?
                SQL
            ,
            ['session'],
            $this->database->basicvalue(json_encode($cmd) . PHP_EOL),
            $uid,
            $this->database->datetime(time() - $this->config->getSetting('updateinterval') * 5),
        );

        return $this->database->affectedRows() !== 0;
    }
}
