<?php

declare(strict_types=1);

namespace Jax\Page\UCP;

use Jax\Database;
use Jax\Date;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_pop;
use function htmlspecialchars;
use function is_array;
use function is_numeric;
use function json_encode;
use function trim;

use const PHP_EOL;

final class Inbox
{
    public function __construct(
        private readonly Database $database,
        private readonly Date $date,
        private readonly Jax $jax,
        private readonly Page $page,
        private readonly Request $request,
        private readonly Template $template,
        private readonly TextFormatting $textFormatting,
        private readonly User $user,
    ) {}

    public function render(): ?string
    {
        $messageId = $this->request->post('messageid');
        $page = $this->request->both('page');
        $view = $this->request->get('view');
        $flag = $this->request->both('flag');
        $dmessage = $this->request->post('dmessage');

        if (is_array($dmessage)) {
            $this->deleteMessages($dmessage);
        }

        return match (true) {
            is_numeric($messageId) => match ($page) {
                'Delete' => $this->delete($messageId),
                'Forward' => $this->compose($messageId, 'fwd'),
                'Reply' => $this->compose($messageId),
            },
            is_numeric($view) => $this->viewMessage($view),
            is_numeric($flag) => $this->flag(),

            default => match ($page) {
                'compose' => $this->compose(),
                'sent' => $this->viewMessages('sent'),
                'flagged' => $this->viewMessages('flagged'),
                default => $this->viewMessages(),
            },
        };
    }

    private function compose(
        string $messageid = '',
        string $todo = '',
    ): ?string {
        $error = null;
        $mid = 0;
        $mname = '';
        $mtitle = '';
        if ($this->request->post('submit') !== null) {
            $mid = $this->request->both('mid');
            if (!$mid && $this->request->both('to')) {
                $result = $this->database->safeselect(
                    [
                        'id',
                        'email',
                        'email_settings',
                    ],
                    'members',
                    'WHERE `display_name`=?',
                    $this->database->basicvalue($this->request->both('to')),
                );
                $udata = $this->database->arow($result);
                $this->database->disposeresult($result);
            } else {
                $result = $this->database->safeselect(
                    [
                        'id',
                        'email',
                        'email_settings',
                    ],
                    'members',
                    Database::WHERE_ID_EQUALS,
                    $this->database->basicvalue($mid),
                );
                $udata = $this->database->arow($result);
                $this->database->disposeresult($result);
            }

            if (!$udata) {
                $error = 'Invalid user!';
            } elseif (
                trim((string) $this->request->both('title')) === ''
                || trim((string) $this->request->both('title')) === '0'
            ) {
                $error = 'You must enter a title.';
            }

            if ($error !== null) {
                $this->page->command('error', $error);
                $this->page->append('PAGE', $this->page->error($error));

                return null;
            }

            // Put it into the table.
            $this->database->safeinsert(
                'messages',
                [
                    'date' => $this->database->datetime(),
                    'del_recipient' => 0,
                    'del_sender' => 0,
                    'from' => $this->user->get('id'),
                    'message' => $this->request->post('message'),
                    'read' => 0,
                    'title' => $this->textFormatting->blockhtml($this->request->post('title')),
                    'to' => $udata['id'],
                ],
            );
            // Give them a notification.
            $cmd = json_encode(
                [
                    'newmessage',
                    'You have a new message from ' . $this->user->get('display_name'),
                    $this->database->insertId(),
                ],
            ) . PHP_EOL;
            $result = $this->database->safespecial(
                <<<'SQL'
                    UPDATE %t
                    SET `runonce`=concat(`runonce`,?)
                    WHERE `uid`=?
                    SQL
                ,
                ['session'],
                $this->database->basicvalue($cmd),
                $udata['id'],
            );
            // Send em an email!
            if (($udata['email_settings'] & 2) !== 0) {
                $this->jax->mail(
                    $udata['email'],
                    'PM From ' . $this->user->get('display_name'),
                    "You are receiving this email because you've "
                    . 'received a message from ' . $this->user->get('display_name')
                    . ' on {BOARDLINK}.<br>'
                    . '<br>Please go to '
                    . "<a href='{BOARDURL}?act=ucp&what=inbox'>"
                    . '{BOARDURL}?act=ucp&what=inbox</a>'
                    . ' to view your message.',
                );
            }

            return 'Message successfully delivered.'
                . "<br><br><a href='?act=ucp&what=inbox'>Back</a>";
        }

        if ($this->request->isJSUpdate() && !$messageid) {
            return null;
        }

        $msg = '';
        if ($messageid !== '' && $messageid !== '0') {
            $result = $this->database->safeselect(
                [
                    '`from`',
                    'message',
                    'title',
                ],
                'messages',
                'WHERE (`to`=? OR `from`=?) AND `id`=?',
                $this->user->get('id'),
                $this->user->get('id'),
                $this->database->basicvalue($messageid),
            );

            $message = $this->database->arow($result);
            $this->database->disposeresult($result);

            $mid = $message['from'];
            $result = $this->database->safeselect(
                ['display_name'],
                'members',
                Database::WHERE_ID_EQUALS,
                $mid,
            );
            $thisrow = $this->database->arow($result);
            $mname = array_pop($thisrow);
            $this->database->disposeresult($result);

            $msg = PHP_EOL . PHP_EOL . PHP_EOL
                . '[quote=' . $mname . ']' . $message['message'] . '[/quote]';
            $mtitle = ($todo === 'fwd' ? 'FWD:' : 'RE:') . $message['title'];
            if ($todo === 'fwd') {
                $mid = '';
                $mname = '';
            }
        }

        if (is_numeric($this->request->get('mid'))) {
            $mid = $this->request->both('mid');
            $result = $this->database->safeselect(
                ['display_name'],
                'members',
                Database::WHERE_ID_EQUALS,
                $mid,
            );
            $thisrow = $this->database->arow($result);
            $mname = array_pop($thisrow);
            $this->database->disposeresult($result);

            if (!$mname) {
                $mid = 0;
                $mname = '';
            }
        }

        return $this->template->meta(
            'inbox-composeform',
            $this->jax->hiddenFormFields(
                [
                    'act' => 'ucp',
                    'page' => 'compose',
                    'submit' => '1',
                    'what' => 'inbox',
                ],
            ),
            $mid,
            $mname,
            $mname ? 'good' : '',
            $mtitle,
            htmlspecialchars($msg),
        );
    }

    private function delete($messageId, bool $relocate = true): void
    {
        $result = $this->database->safeselect(
            [
                '`to`',
                '`from`',
                'del_recipient',
                'del_sender',
            ],
            'messages',
            Database::WHERE_ID_EQUALS,
            $this->database->basicvalue($messageId),
        );
        $message = $this->database->arow($result);
        $this->database->disposeresult($result);

        $is_recipient = $message['to'] === $this->user->get('id');
        $is_sender = $message['from'] === $this->user->get('id');
        if ($is_recipient) {
            $this->database->safeupdate(
                'messages',
                [
                    'del_recipient' => 1,
                ],
                Database::WHERE_ID_EQUALS,
                $this->database->basicvalue($messageId),
            );
        }

        if ($is_sender) {
            $this->database->safeupdate(
                'messages',
                [
                    'del_sender' => 1,
                ],
                Database::WHERE_ID_EQUALS,
                $this->database->basicvalue($messageId),
            );
        }

        $result = $this->database->safeselect(
            [
                'del_recipient',
                'del_sender',
            ],
            'messages',
            Database::WHERE_ID_EQUALS,
            $this->database->basicvalue($messageId),
        );
        $message = $this->database->arow($result);
        $this->database->disposeresult($result);

        if ($message['del_recipient'] && $message['del_sender']) {
            $this->database->safedelete(
                'messages',
                Database::WHERE_ID_EQUALS,
                $this->database->basicvalue($messageId),
            );
        }

        if (!$relocate) {
            return;
        }

        $this->page->location(
            '?act=ucp&what=inbox'
            . ($this->request->both('prevpage') !== null
            ? '&page=' . $this->request->both('prevpage') : ''),
        );
    }

    private function deleteMessages(array $messageIds): void
    {
        foreach ($messageIds as $messageId) {
            $this->delete($messageId, false);
        }
    }

    private function fetchMessages($view)
    {
        $criteria = match ($view) {
            'sent' => <<<'SQL'
                LEFT JOIN %t m ON a.`to`=m.`id`
                WHERE a.`from`=? AND !a.`del_sender`
                SQL,
            'flagged' => <<<'SQL'
                LEFT JOIN %t m ON a.`from`=m.`id`
                WHERE a.`to`=? AND a.`flag`=1
                SQL,
            default => <<<'SQL'
                LEFT JOIN %t m ON a.`from`=m.`id`
                WHERE a.`to`=? AND !a.`del_recipient`
                SQL,
        };

        $result = $this->database->safespecial(
            <<<"SQL"
                SELECT
                    a.`id` AS `id`,
                    a.`to` AS `to`,
                    a.`from` AS `from`,
                    a.`title` AS `title`,
                    a.`message` AS `message`,
                    a.`read` AS `read`,
                    UNIX_TIMESTAMP(a.`date`) AS `date`,
                    a.`del_recipient` AS `del_recipient`,
                    a.`del_sender` AS `del_sender`,
                    a.`flag` AS `flag`,
                    m.`display_name` AS `display_name`
                FROM %t a
                {$criteria}
                ORDER BY a.`date` DESC
                SQL,
            ['messages', 'members'],
            $this->user->get('id'),
        );

        return $this->database->arows($result);
    }

    private function flag(): null
    {
        $this->page->command('softurl');
        $this->database->safeupdate(
            'messages',
            [
                'flag' => $this->request->both('tog') ? 1 : 0,
            ],
            'WHERE `id`=? AND `to`=?',
            $this->database->basicvalue($this->request->both('flag')),
            $this->user->get('id'),
        );

        return null;
    }

    private function updateNumMessages(): void
    {
        $result = $this->database->safeselect(
            'COUNT(`id`)',
            'messages',
            'WHERE `to`=? AND !`read`',
            $this->user->get('id'),
        );
        $unread = $this->database->arow($result);
        $this->database->disposeresult($result);

        $unread = array_pop($unread);
        $this->page->command('update', 'num-messages', $unread);
    }

    private function viewMessage(string $messageid): ?string
    {
        if (
            $this->request->isJSUpdate()
            && !$this->request->isJSDirectLink()
        ) {
            return null;
        }

        $error = null;
        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT a.`id` AS `id`,a.`to` AS `to`,a.`from` AS `from`,a.`title` AS `title`,
                    a.`message` AS `message`,a.`read` AS `read`,
                    UNIX_TIMESTAMP(a.`date`) AS `date`,a.`del_recipient` AS `del_recipient`,
                    a.`del_sender` AS `del_sender`,a.`flag` AS `flag`,
                    m.`group_id` AS `group_id`,m.`display_name` AS `name`,
                    m.`avatar` AS `avatar`,m.`usertitle` AS `usertitle`
                FROM %t a
                LEFT JOIN %t m ON a.`from`=m.`id`
                WHERE a.`id`=?
                ORDER BY a.`date` DESC
                SQL
            ,
            ['messages', 'members'],
            $this->database->basicvalue($messageid),
        );
        $message = $this->database->arow($result);
        $this->database->disposeresult($result);
        if (
            $message['from'] !== $this->user->get('id')
            && $message['to'] !== $this->user->get('id')
        ) {
            $error = "You don't have permission to view this message.";
        }

        if ($error !== null) {
            return $error;
        }

        if (!$message['read'] && $message['to'] === $this->user->get('id')) {
            $this->database->safeupdate(
                'messages',
                ['read' => 1],
                Database::WHERE_ID_EQUALS,
                $message['id'],
            );
            $this->updateNumMessages();
        }

        return $this->template->meta(
            'inbox-messageview',
            $message['title'],
            $this->template->meta(
                'user-link',
                $message['from'],
                $message['group_id'],
                $message['name'],
            ),
            $this->date->autoDate($message['date']),
            $this->textFormatting->theWorks($message['message']),
            $message['avatar'] ?: $this->template->meta('default-avatar'),
            $message['usertitle'],
            $this->jax->hiddenFormFields(
                [
                    'act' => 'ucp',
                    'messageid' => $message['id'],
                    'sender' => $message['from'],
                    'what' => 'inbox',
                ],
            ),
        );
    }

    private function viewMessages(string $view = 'inbox'): ?string
    {
        $page = '';
        $result = null;
        $hasmessages = false;

        $unread = 0;
        foreach ($this->fetchMessages($view) as $message) {
            $hasmessages = 1;
            if (!$message['read']) {
                ++$unread;
            }

            $dmessageOnchange = "RUN.stream.location('"
                . '?act=ucp&what=inbox&flag=' . $message['id'] . "&tog='+" . '
                (this.checked?1:0), 1)';
            $page .= $this->template->meta(
                'inbox-messages-row',
                $message['read'] ? 'read' : 'unread',
                '<input class="check" type="checkbox" title="PM Checkbox" name="dmessage[]" '
                . 'value="' . $message['id'] . '" />',
                '<input type="checkbox" '
                . ($message['flag'] ? 'checked="checked" ' : '')
                . 'class="switch flag" onchange="' . $dmessageOnchange . '" />',
                $message['id'],
                $message['title'],
                $message['display_name'],
                $this->date->autoDate($message['date']),
            );
        }

        if (!$hasmessages) {
            $msg = match ($view) {
                'sent' => 'No sent messages.',
                'flagged' => 'No flagged messages.',
                default => 'No messages. You could always try '
                    . '<a href="?act=ucp&what=inbox&page=compose">'
                    . 'sending some</a>, though!',
            };

            $page .= '<tr><td colspan="5" class="error">' . $msg . '</td></tr>';
        }

        $page = $this->template->meta(
            'inbox-messages-listing',
            $this->jax->hiddenFormFields(
                [
                    'act' => 'ucp',
                    'what' => 'inbox',
                ],
            ),
            $view === 'sent' ? 'Recipient' : 'Sender',
            $page,
        );

        if ($view === 'inbox') {
            $this->page->command('update', 'num-messages', $unread);
        }

        return $page;
    }
}
