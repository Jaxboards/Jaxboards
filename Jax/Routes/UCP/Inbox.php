<?php

declare(strict_types=1);

namespace Jax\Routes\UCP;

use Jax\Database\Database;
use Jax\DomainDefinitions;
use Jax\Jax;
use Jax\Lodash;
use Jax\Models\Member;
use Jax\Models\Message;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Template;
use Jax\User;

use function array_map;
use function ceil;
use function implode;
use function is_array;
use function is_numeric;
use function json_encode;
use function max;
use function trim;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

final readonly class Inbox
{
    public const int MESSAGES_PER_PAGE = 10;

    public function __construct(
        private Database $database,
        private DomainDefinitions $domainDefinitions,
        private Jax $jax,
        private Page $page,
        private Request $request,
        private Router $router,
        private Template $template,
        private User $user,
    ) {}

    public function render(): ?string
    {
        $messageId = (int) $this->request->asString->both('messageid');
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
                'Forward' => $this->compose($messageId, 'forward'),
                'Reply' => $this->compose($messageId, 'reply'),
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

    /**
     * Return string on error, null on success.
     */
    private function sendMessage(?Member $member): ?string
    {
        if ($member === null) {
            return 'Invalid user!';
        }

        $title = $this->request->asString->post('title');

        if (trim($title ?? '') === '') {
            return 'You must enter a title.';
        }

        // Put it into the table.
        $message = new Message();
        $message->date = $this->database->datetime();
        $message->deletedRecipient = 0;
        $message->deletedSender = 0;
        $message->from = $this->user->get()->id;
        $message->message = $this->request->asString->post('message') ?? '';
        $message->read = 0;
        $message->title = $title;
        $message->to = $member->id;
        $message->insert();

        // Give them a notification.
        $cmd = json_encode(
            [
                'newmessage',
                'You have a new message from ' . $this->user->get()->displayName,
                $message->id,
            ],
            JSON_THROW_ON_ERROR,
        ) . PHP_EOL;

        $this->database->special(
            <<<'SQL'
                UPDATE %t
                SET `runonce`=concat(`runonce`,?)
                WHERE `uid`=?
                SQL,
            ['session'],
            $cmd,
            $member->id,
        );

        $inboxURL = $this->domainDefinitions->getBoardUrl() . $this->router->url(
            'inbox',
        );

        // Send em an email!
        if (($member->emailSettings & 2) !== 0) {
            $fromName = $this->user->get()->displayName;
            $this->jax->mail(
                $member->email,
                "PM From {$fromName}",
                <<<HTML
                    You are receiving this email because you've
                    received a message from {$fromName} on {BOARDLINK}<br>
                    Please go to <a href='{$inboxURL}'>{$inboxURL}</a>
                    to view your message.
                    HTML,
            );
        }

        return null;
    }

    private function compose(
        ?int $messageId = null,
        string $todo = '',
    ): ?string {
        $error = null;
        $recipient = null;


        $mid = (int) $this->request->asString->both('mid');
        $to = $this->request->asString->both('to');

        if ($mid !== 0) {
            $recipient = Member::selectOne($mid);
        }

        $sentMessage = $this->request->post('submit') !== null;

        if ($sentMessage) {
            $recipient ??= $to
                ? Member::selectOne('WHERE `displayName`=?', $to)
                : null;

            $error = $this->sendMessage($recipient);
        }

        if ($this->request->isJSUpdate() && !$messageId) {
            return null;
        }

        $messageTitle = '';
        $messageBody = '';
        if ($messageId) {
            $message = Message::selectOne(
                'WHERE (`to`=? OR `from`=?) AND `id`=?',
                $this->user->get()->id,
                $this->user->get()->id,
                $messageId,
            );

            if ($message !== null) {
                $sender = Member::selectOne($message->from);

                if ($todo === 'reply') {
                    $recipient = $sender;
                }

                $messageTitle = ($todo === 'reply' ? 'RE:' : 'FWD:') . $message->title;
                $messageBody = PHP_EOL . PHP_EOL . PHP_EOL
                    . "[quote={$sender->displayName}]{$message->message}[/quote]";
            }
        }

        return $this->template->render(
            'inbox/compose',
            [
                'error' => $error,
                'success' => $sentMessage && $error === null,
                'recipient' => $recipient,
                'messageTitle' => $messageTitle,
                'messageBody' => $messageBody,
            ],
        );
    }

    private function delete(int $messageId, bool $relocate = true): void
    {
        $message = Message::selectOne($messageId);

        if ($message === null) {
            return;
        }

        $isRecipient = $message->to === $this->user->get()->id;
        $isSender = $message->from === $this->user->get()->id;

        if ($isRecipient) {
            $message->deletedRecipient = 1;
            $message->update();
        }

        if ($isSender) {
            $message->deletedSender = 1;
            $message->update();
        }

        if ($message->deletedRecipient && $message->deletedSender) {
            $message->delete();
        }

        if (!$relocate) {
            return;
        }

        $this->router->redirect(
            'inbox',
            [
                'page' => $this->request->asString->both('prevpage') ?? '',
            ],
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
            'sent' => 'WHERE `from`=? AND `deletedSender`=0',
            'flagged' => 'WHERE `to`=? AND `flag`=1',
            'unread' => 'WHERE `to`=? AND `read`=0',
            'read' => 'WHERE `to`=? AND `read`=1',
            default => 'WHERE `to`=? AND `deletedRecipient`=0',
        };

        return Message::count($criteria, $this->user->get()->id);
    }

    /**
     * @return array<Message>
     */
    private function fetchMessages(string $view, int $pageNumber = 0): array
    {
        $criteria = match ($view) {
            'sent' => 'WHERE `from`=? AND `deletedSender`=0',
            'flagged' => 'WHERE `to`=? AND flag=1',
            default => 'WHERE `to`=? AND deletedRecipient=0',
        };

        return Message::selectMany(
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
            'WHERE `id`=? AND `to`=?',
            $messageId,
            $this->user->get()->id,
        );

        if ($message !== null) {
            $message->flag = $this->request->both('tog') ? 1 : 0;
            $message->update();
        }

        if (!$this->request->isJSAccess()) {
            $this->router->redirect('inbox');
        }

        return null;
    }

    private function viewMessage(string $messageId): ?string
    {
        if (
            $this->request->isJSUpdate()
            && !$this->request->isJSDirectLink()
        ) {
            return null;
        }

        $message = Message::selectOne(
            'WHERE `id`=?
            ORDER BY `date` DESC',
            $messageId,
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
            $userIsRecipient ? $message->from : $message->to,
        );

        if (!$message->read && $userIsRecipient) {
            $message->read = 1;
            $message->update();

            $this->page->command(
                'update',
                'num-messages',
                $this->fetchMessageCount('unread'),
            );
        }

        return $this->template->render(
            'inbox/message-view',
            [
                'message' => $message,
                'otherMember' => $otherMember,
            ],
        );
    }

    private function viewMessages(string $view = 'inbox'): string
    {
        $requestPage = max(1, (int) $this->request->asString->both('page'));
        $numMessages = $this->fetchMessageCount($view);

        $pages = $numMessages !== 0 ? 'Pages: ' : '';
        $pageNumbers = $this->jax->pages(
            (int) ceil($numMessages / self::MESSAGES_PER_PAGE),
            $requestPage,
            10,
        );

        $pages .= implode(
            ' &middot; ',
            array_map(
                function (int $pageNumber) use ($requestPage, $view): string {
                    $active = $pageNumber === $requestPage ? ' class="active"' : '';
                    $pageURL = $this->router->url('ucp', [
                        'view' => $view,
                        'page' => $pageNumber,
                    ]);

                    return <<<HTML
                        <a href="{$pageURL}" {$active}>{$pageNumber}</a>
                        HTML;
                },
                $pageNumbers,
            ),
        );

        $messages = $this->fetchMessages($view, $requestPage - 1);

        $getMessageMemberId = $view === 'sent'
            ? static fn(Message $message): ?int => $message->to
            : static fn(Message $message): ?int => $message->from;

        $membersById = Member::joinedOn(
            $messages,
            $getMessageMemberId,
        );

        $readCounts = Lodash::countBy(
            $messages,
            static fn(Message $message): string => $message->read !== 0 ? 'read' : 'unread',
        );
        $rows = array_map(static fn(Message $message): array => [
            'message' => $message,
            'otherMember' => $membersById[$getMessageMemberId($message)],
        ], $messages);

        if ($view === 'inbox') {
            $this->page->command(
                'update',
                'num-messages',
                $readCounts['unread'] ?? 0,
            );
        }

        return $this->template->render(
            'inbox/messages-listing',
            [
                'pages' => $pages,
                'view' => $view,
                'rows' => $rows,
            ],
        );
    }
}
