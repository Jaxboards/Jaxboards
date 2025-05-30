<?php

declare(strict_types=1);

namespace Jax\Page\UCP;

use Jax\Database;
use Jax\Date;
use Jax\Jax;
use Jax\Models\Member;
use Jax\Models\Message;
use Jax\Page;
use Jax\Request;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function array_map;
use function ceil;
use function htmlspecialchars;
use function implode;
use function is_array;
use function is_numeric;
use function json_encode;
use function max;
use function trim;

use const PHP_EOL;

final readonly class Inbox
{
    public const MESSAGES_PER_PAGE = 10;

    public function __construct(
        private Database $database,
        private Date $date,
        private Jax $jax,
        private Page $page,
        private Request $request,
        private Template $template,
        private TextFormatting $textFormatting,
        private User $user,
    ) {}

    public function render(): ?string
    {
        $messageId = (int) $this->request->asString->post('messageid');
        $view = $this->request->asString->both('view');
        $page = $this->request->asString->both('page');
        $flag = (int) $this->request->asString->both('flag');
        $dmessage = $this->request->post('dmessage');

        if (is_array($dmessage)) {
            $this->deleteMessages(array_map(
                static fn($messageId): int => (int) $messageId,
                $dmessage,
            ));
        }

        return match (true) {
            $messageId !== 0 => match ($page) {
                'Delete' => $this->delete($messageId),
                'Forward' => $this->compose($messageId, 'fwd'),
                'Reply' => $this->compose($messageId),
                default => null,
            },
            is_numeric($view) => $this->viewMessage($view),
            $flag !== 0 => $this->flag($flag),

            default => match ($view) {
                'compose' => $this->compose(),
                'sent' => $this->viewMessages('sent'),
                'flagged' => $this->viewMessages('flagged'),
                default => $this->viewMessages(),
            },
        };
    }

    private function compose(
        ?int $messageid = null,
        string $todo = '',
    ): ?string {
        $error = null;
        $mname = '';
        $mtitle = '';
        $mid = 0;
        if ($this->request->post('submit') !== null) {
            $mid = (int) $this->request->asString->both('mid');
            $to = $this->request->asString->both('to');
            $udata = !$mid && $to
                ? Member::selectOne($this->database, 'WHERE `display_name`=?', $to)
                : Member::selectOne($this->database, Database::WHERE_ID_EQUALS, $mid);

            $error = match (true) {
                !$udata => 'Invalid user!',
                trim((string) $this->request->asString->both('title')) === '' => 'You must enter a title.',
                default => null,
            };

            if ($error !== null) {
                $this->page->command('error', $error);
                $this->page->append('PAGE', $this->page->error($error));

                return null;
            }

            if ($udata === null) {
                return null;
            }

            $title = $this->request->asString->post('title');

            // Put it into the table.
            $message = new Message();
            $message->date = $this->database->datetime();
            $message->del_recipient = 0;
            $message->del_sender = 0;
            $message->from = $this->user->get()->id;
            $message->message = $this->request->asString->post('message') ?? '';
            $message->read = 0;
            $message->title = $title
                ? $this->textFormatting->blockhtml($title)
                : '';
            $message->to = $udata->id;
            $message->insert($this->database);

            // Give them a notification.
            $cmd = json_encode(
                [
                    'newmessage',
                    'You have a new message from ' . $this->user->get()->display_name,
                    $message->id,
                ],
            ) . PHP_EOL;
            $this->database->special(
                <<<'SQL'
                    UPDATE %t
                    SET `runonce`=concat(`runonce`,?)
                    WHERE `uid`=?
                    SQL,
                ['session'],
                $cmd,
                $udata->id,
            );
            // Send em an email!
            if (($udata->email_settings & 2) !== 0) {
                $this->jax->mail(
                    $udata->email,
                    'PM From ' . $this->user->get()->display_name,
                    "You are receiving this email because you've "
                        . 'received a message from ' . $this->user->get()->display_name
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
        if ($messageid) {
            $message = Message::selectOne(
                $this->database,
                'WHERE (`to`=? OR `from`=?) AND `id`=?',
                $this->user->get()->id,
                $this->user->get()->id,
                $messageid,
            );

            if ($message !== null) {
                $mid = $message->from;
                $member = Member::selectOne($this->database, Database::WHERE_ID_EQUALS, $mid);
                $mname = $member?->display_name;

                $msg = PHP_EOL . PHP_EOL . PHP_EOL
                    . '[quote=' . $mname . ']' . $message->message . '[/quote]';
                $mtitle = ($todo === 'fwd' ? 'FWD:' : 'RE:') . $message->title;
                if ($todo === 'fwd') {
                    $mid = '';
                    $mname = '';
                }
            }
        }

        if (is_numeric($this->request->asString->get('mid'))) {
            $mid = (int) $this->request->asString->both('mid');
            $member = Member::selectOne($this->database, Database::WHERE_ID_EQUALS, $mid);
            $mname = $member?->display_name;

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
                    'view' => 'compose',
                    'submit' => '1',
                    'what' => 'inbox',
                ],
            ),
            $mid,
            $mname,
            $mname !== '' ? 'good' : '',
            $mtitle,
            htmlspecialchars($msg),
        );
    }

    private function delete(int $messageId, bool $relocate = true): void
    {
        $message = Message::selectOne(
            $this->database,
            Database::WHERE_ID_EQUALS,
            $messageId,
        );

        if ($message === null) {
            return;
        }

        $isRecipient = $message->to === $this->user->get()->id;
        $isSender = $message->from === $this->user->get()->id;

        if ($isRecipient) {
            $message->del_recipient = 1;
            $message->update($this->database);
        }

        if ($isSender) {
            $message->del_sender = 1;
            $message->update($this->database);
        }

        if ($message->del_recipient && $message->del_sender) {
            $message->delete($this->database);
        }

        if (!$relocate) {
            return;
        }

        $this->page->location(
            '?act=ucp&what=inbox'
                . ($this->request->both('prevpage') !== null
                    ? '&page=' . $this->request->asString->both('prevpage') : ''),
        );
    }

    /**
     * @param array<int> $messageIds
     */
    private function deleteMessages(array $messageIds): void
    {
        foreach ($messageIds as $messageId) {
            $this->delete($messageId, false);
        }
    }

    private function fetchMessageCount(?string $view = null): int
    {
        $criteria = match ($view) {
            'sent' => 'WHERE `from`=? AND !`del_sender`',
            'flagged' => 'WHERE `to`=? AND `flag`=1',
            'unread' => 'WHERE `to`=? AND !`read`',
            'read' => 'WHERE `to`=? AND `read`=1',
            default => 'WHERE `to`=? AND !`del_recipient`',
        };

        return Message::count($this->database, $criteria, $this->user->get()->id) ?? 0;
    }

    /**
     * @return array<Message>
     */
    private function fetchMessages(string $view, int $pageNumber = 0): array
    {
        $criteria = match ($view) {
            'sent' => 'WHERE `from`=? AND !del_sender',
            'flagged' => 'WHERE `to`=? AND flag=1',
            default => 'WHERE `to`=? AND !del_recipient',
        };

        return Message::selectMany(
            $this->database,
            "{$criteria}
                ORDER BY date DESC
                LIMIT ?, ?",
            $this->user->get()->id,
            $pageNumber * self::MESSAGES_PER_PAGE,
            self::MESSAGES_PER_PAGE,
        );
    }

    private function flag(int $messageId): null
    {
        $this->page->command('softurl');

        $message = Message::selectOne(
            $this->database,
            'WHERE `id`=? AND `to`=?',
            $messageId,
            $this->user->get()->id,
        );

        if ($message !== null) {
            $message->flag = $this->request->both('tog') ? 1 : 0;
            $message->update($this->database);
        }

        return null;
    }

    private function viewMessage(string $messageid): ?string
    {
        if (
            $this->request->isJSUpdate()
            && !$this->request->isJSDirectLink()
        ) {
            return null;
        }

        $message = Message::selectOne(
            $this->database,
            'WHERE `id`=?
            ORDER BY `date` DESC',
            $messageid,
        );

        if ($message === null) {
            return 'This message does not exist';
        }

        $userIsRecipient = $message->to === $this->user->get()->id;
        $userIsSender = $message->from === $this->user->get()->id;

        if (!$userIsRecipient && !$userIsSender) {
            return "You don't have permission to view this message.";
        }

        $otherMember = Member::selectOne(
            $this->database,
            Database::WHERE_ID_EQUALS,
            $userIsRecipient ? $message->to : $message->from,
        );

        if (!$message->read && $userIsRecipient) {
            $this->database->update(
                'messages',
                ['read' => 1],
                Database::WHERE_ID_EQUALS,
                $message->id,
            );
            $this->page->command('update', 'num-messages', $this->fetchMessageCount('unread'));
        }

        return $this->template->meta(
            'inbox-messageview',
            $message->title,
            $otherMember !== null ? $this->template->meta(
                'user-link',
                $otherMember->id,
                $otherMember->group_id,
                $otherMember->name,
            ) : '',
            $this->date->autoDate($this->database->datetimeAsTimestamp($message->date)),
            $this->textFormatting->theWorks($message->message),
            $otherMember?->avatar ?: $this->template->meta('default-avatar'),
            $otherMember?->usertitle,
            $this->jax->hiddenFormFields(
                [
                    'act' => 'ucp',
                    'messageid' => (string) $message->id,
                    'sender' => (string) $message->from,
                    'what' => 'inbox',
                ],
            ),
        );
    }

    private function viewMessages(string $view = 'inbox'): string
    {
        $html = '';

        $requestPage = max(1, (int) $this->request->asString->both('page'));
        $numMessages = $this->fetchMessageCount($view);

        $pages = 'Pages: ';
        $pageNumbers = $this->jax->pages(
            (int) ceil($numMessages / self::MESSAGES_PER_PAGE),
            $requestPage,
            10,
        );

        $pages .= implode(' &middot; ', array_map(static function ($pageNumber) use ($requestPage, $view): string {
            $active = $pageNumber === $requestPage ? ' class="active"' : '';

            return <<<HTML
                <a href="?act=ucp&what=inbox&view={$view}&page={$pageNumber}" {$active}>{$pageNumber}</a>
                HTML;
        }, $pageNumbers));

        $unread = 0;
        $messages = $this->fetchMessages($view, $requestPage - 1);

        $getMessageMemberId = $view === 'sent'
            ? static fn(Message $message): ?int => $message->to
            : static fn(Message $message): ?int => $message->from;

        $membersById = Member::joinedOn(
            $this->database,
            $messages,
            $getMessageMemberId
        );

        foreach ($messages as $message) {
            if (!$message->read) {
                ++$unread;
            }

            $otherMember = $membersById[$getMessageMemberId($message)];

            $dmessageOnchange = "RUN.stream.location('"
                . '?act=ucp&what=inbox&flag=' . $message->id . "&tog='+" . '
                (this.checked?1:0), 1)';
            $html .= $this->template->meta(
                'inbox-messages-row',
                $message->read ? 'read' : 'unread',
                '<input class="check" type="checkbox" title="PM Checkbox" name="dmessage[]" '
                    . 'value="' . $message->id . '" />',
                '<input type="checkbox" '
                    . ($message->flag ? 'checked="checked" ' : '')
                    . 'class="switch flag" onchange="' . $dmessageOnchange . '" />',
                $message->id,
                $message->title,
                $otherMember->display_name,
                $this->date->autoDate($this->database->datetimeAsTimestamp($message->date)),
            );
        }

        if ($messages === []) {
            $msg = match ($view) {
                'sent' => 'No sent messages.',
                'flagged' => 'No flagged messages.',
                default => 'No messages. You could always try '
                    . '<a href="?act=ucp&what=inbox&view=compose">'
                    . 'sending some</a>, though!',
            };

            $html .= '<tr><td colspan="5" class="error">' . $msg . '</td></tr>';
        }

        $html = $this->template->meta(
            'inbox-messages-listing',
            $this->jax->hiddenFormFields(
                [
                    'act' => 'ucp',
                    'what' => 'inbox',
                ],
            ),
            $pages,
            $view === 'sent' ? 'Recipient' : 'Sender',
            $html,
        );

        if ($view === 'inbox') {
            $this->page->command('update', 'num-messages', $unread);
        }

        return $html;
    }
}
