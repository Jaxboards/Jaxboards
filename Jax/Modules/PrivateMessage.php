<?php

declare(strict_types=1);

namespace Jax\Modules;

use Carbon\Carbon;
use Jax\Database;
use Jax\Page;
use Jax\Request;
use Jax\Session;
use Jax\TextFormatting;
use Jax\User;

use function explode;
use function implode;
use function in_array;
use function json_decode;
use function json_encode;
use function trim;

use const PHP_EOL;

final readonly class PrivateMessage
{
    public function __construct(
        private Database $database,
        private Page $page,
        private Request $request,
        private Session $session,
        private TextFormatting $textFormatting,
        private User $user,
    ) {}

    public function init(): void
    {
        $instantMessage = $this->request->asString->post('im_im');
        $uid = (int) $this->request->asString->post('im_uid');
        if (
            $this->session->get()->runonce !== ''
            && $this->session->get()->runonce !== '0'
        ) {
            $this->filter();
        }

        if (!$instantMessage || trim($instantMessage) === '' || !$uid) {
            return;
        }

        $this->message($uid, $instantMessage);
    }

    public function filter(): void
    {
        $enemies = $this->user->get()->enemies;
        if (!$enemies) {
            return;
        }

        $enemies = explode(',', (string) $enemies);
        $commands = explode(PHP_EOL, $this->session->get()->runonce);
        foreach ($commands as $index => $command) {
            $command = json_decode($command);
            if (!$command) {
                continue;
            }

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

    public function message(int $uid, string $instantMessage): void
    {
        $this->session->act();

        if ($this->user->isGuest()) {
            $this->page->command('error', 'You must be logged in to instant message!');

            return;
        }

        if ($uid === 0) {
            $this->page->command('error', 'You must have a recipient!');

            return;
        }

        if (!$this->user->getPerm('can_im')) {
            $this->page->command(
                'error',
                "You don't have permission to use this feature.",
            );

            return;
        }

        $instantMessage = $this->textFormatting->linkify($instantMessage);
        $instantMessage = $this->textFormatting->theWorks($instantMessage);

        $cmd = [
            'im',
            $uid,
            $this->user->get()->display_name,
            $instantMessage,
            $this->user->get()->id,
            Carbon::now('UTC')->getTimestamp(),
        ];
        $this->page->command(...$cmd);
        $cmd[1] = $this->user->get()->id;
        $cmd[4] = 0;

        if ($this->sendcmd($cmd, $uid)) {
            return;
        }

        $this->page->command('imtoggleoffline', $uid);
    }

    /**
     * @param array<mixed> $cmd
     */
    public function sendcmd(array $cmd, int $uid): bool
    {
        $result = $this->database->special(
            <<<'SQL'
                UPDATE %t
                SET `runonce`=CONCAT(`runonce`,?)
                WHERE `uid`=? AND `last_update`>?
                SQL,
            ['session'],
            json_encode($cmd) . PHP_EOL,
            $uid,
            $this->database->datetime(Carbon::now()->subSeconds(10)->getTimestamp()),
        );

        return $this->database->affectedRows($result) !== 0;
    }
}
