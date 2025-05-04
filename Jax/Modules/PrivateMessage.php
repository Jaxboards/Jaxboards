<?php

declare(strict_types=1);

namespace Jax\Modules;

use Carbon\Carbon;
use Jax\Config;
use Jax\Database;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\TextFormatting;
use Jax\User;

use function explode;
use function implode;
use function in_array;
use function is_numeric;
use function json_decode;
use function json_encode;
use function trim;

use const PHP_EOL;

final readonly class PrivateMessage
{
    public function __construct(
        private Config $config,
        private Database $database,
        private Page $page,
        private Request $request,
        private Session $session,
        private TextFormatting $textFormatting,
        private User $user,
    ) {}

    public function init(): void
    {
        $instantMessage = $this->request->post('im_im');
        $uid = $this->request->post('im_uid');
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
        $commands = explode(PHP_EOL, (string) $this->session->get('runonce'));
        foreach ($commands as $index => $command) {
            $command = json_decode($command);
            if ($command[0] !== 'im') {
                continue;
            }

            unset($commands[$index]);
            if (in_array($command[1], $enemies)) {
                continue;
            }

            $this->page->command(...$command);
        }

        $this->session->set('runonce', implode(PHP_EOL, $commands));
    }

    public function message($uid, $instantMessage)
    {
        $this->session->act();
        $error = null;
        $fatal = false;

        if ($this->user->isGuest()) {
            return $this->page->command('error', 'You must be logged in to instant message!');
        }

        if (!$uid) {
            return $this->page->command('error', 'You must have a recipient!');
        }

        if (!$this->user->getPerm('can_im')) {
            return $this->page->command(
                'error',
                "You don't have permission to use this feature.",
            );
        }

        $instantMessage = $this->textFormatting->linkify($instantMessage);
        $instantMessage = $this->textFormatting->theWorks($instantMessage);

        $cmd = [
            'im',
            $uid,
            $this->user->get('display_name'),
            $instantMessage,
            $this->user->get('id'),
            Carbon::now()->getTimestamp(),
        ];
        $this->page->command(...$cmd);
        $cmd[1] = $this->user->get('id');
        $cmd[4] = 0;
        $onlineusers = $this->database->getUsersOnline();
        $logoutTime = Carbon::now()->getTimestamp() - $this->config->getSetting('timetologout');
        $updateTime = Carbon::now()->subSeconds(5)->getTimestamp();
        if (
            !isset($onlineusers[$uid])
            || !$onlineusers[$uid]
            || $onlineusers[$uid]['last_update'] < $logoutTime
            || $onlineusers[$uid]['last_update'] < $updateTime
        ) {
            $this->page->command('imtoggleoffline', $uid);
        }

        if (!$this->sendcmd($cmd, $uid)) {
            $this->page->command('imtoggleoffline', $uid);
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
            $this->database->datetime(Carbon::now()->subSeconds(5)->getTimestamp()),
        );

        return $this->database->affectedRows() !== 0;
    }
}
