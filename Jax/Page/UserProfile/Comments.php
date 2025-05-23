<?php

declare(strict_types=1);

namespace Jax\Page\UserProfile;

use Jax\Database;
use Jax\Date;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

final class Comments
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

    /**
     * @param array<string,null|float|int|string> $profile
     */
    public function render(array $profile): string
    {
        $tabHTML = '';

        $this->handleCommentDeletion();
        $tabHTML .= $this->handleCommentCreation($profile);

        if (
            !$this->user->isGuest()
            && $this->user->getPerm('can_add_comments')
        ) {
            $tabHTML .= $this->template->meta(
                'userprofile-comment-form',
                $this->user->get('name') ?? '',
                $this->user->get('avatar') ?: $this->template->meta('default-avatar'),
                $this->jax->hiddenFormFields(
                    [
                        'act' => 'vu' . $profile['id'],
                        'page' => 'comments',
                        'view' => 'profile',
                    ],
                ),
            );
        }

        $comments = $this->fetchComments($profile);

        if (!$comments) {
            return $tabHTML . 'No comments to display!';
        }

        foreach ($comments as $comment) {
            $act = $this->request->asString->both('act');
            $deleteLink = $this->user->getPerm('can_delete_comments')
                && $comment['from'] === $this->user->get('id')
                || $this->user->getPerm('can_moderate') ? <<<HTML
                    <a href="?act={$act}&page=comments&del={$comment['id']}" class="delete">[X]</a>
                    HTML
                : '';

            $tabHTML .= $this->template->meta(
                'userprofile-comment',
                $this->template->meta(
                    'user-link',
                    $comment['from'],
                    $comment['group_id'],
                    $comment['display_name'],
                ),
                $comment['avatar'] ?: $this->template->meta('default-avatar'),
                $this->date->autoDate($comment['date']),
                $this->textFormatting->theWorks($comment['comment']),
                $deleteLink,
            );
        }

        return $tabHTML;
    }

    /**
     * @param array<string,null|float|int|string> $profile
     * @return array<array<string,mixed>>
     */
    private function fetchComments(array $profile): array
    {
        $result = $this->database->safespecial(
            <<<'SQL'
                SELECT
                    c.`id` AS `id`,
                    c.`to` AS `to`,
                    c.`from` AS `from`,
                    c.`comment` AS `comment`,
                    UNIX_TIMESTAMP(c.`date`) AS `date`,
                    m.`display_name` AS `display_name`,
                    m.`group_id` AS `group_id`,
                    m.`avatar` AS `avatar`
                FROM %t c
                LEFT JOIN %t m
                    ON c.`from`=m.`id`
                WHERE c.`to`=?
                ORDER BY c.`id` DESC
                LIMIT 10
                SQL,
            ['profile_comments', 'members'],
            $profile['id'],
        );
        $comments = $this->database->arows($result);
        $this->database->disposeresult($result);

        return $comments;
    }

    /**
     * @param array<string,null|float|int|string> $profile
     */
    private function handleCommentCreation(array $profile): string
    {
        $comment = $this->request->asString->post('comment');
        if (!$comment) {
            return '';
        }

        $error = null;
        if (
            $this->user->isGuest()
            || !$this->user->getPerm('can_add_comments')
        ) {
            $error = 'No permission to add comments!';
        }

        if ($error !== null) {
            $this->page->command('error', $error);

            return $this->template->meta('error', $error);
        }

        $this->database->safeinsert(
            'activity',
            [
                'affected_uid' => $profile['id'],
                'date' => $this->database->datetime(),
                'type' => 'profile_comment',
                'uid' => $this->user->get('id'),
            ],
        );
        $this->database->safeinsert(
            'profile_comments',
            [
                'comment' => $comment,
                'date' => $this->database->datetime(),
                'from' => $this->user->get('id'),
                'to' => $profile['id'],
            ],
        );

        return '';
    }

    private function handleCommentDeletion(): void
    {
        $deleteComment = (int) $this->request->asString->both('del');
        if ($deleteComment === 0) {
            return;
        }

        // Moderators can delete any comment
        if ($this->user->getPerm('can_moderate')) {
            $this->database->safedelete(
                'profile_comments',
                Database::WHERE_ID_EQUALS,
                $this->database->basicvalue($deleteComment),
            );

            return;
        }

        if (!$this->user->getPerm('can_delete_comments')) {
            return;
        }

        // Delete only own comments
        $this->database->safedelete(
            'profile_comments',
            'WHERE `id`=? AND `from`=?',
            $this->database->basicvalue($deleteComment),
            $this->database->basicvalue($this->user->get('id')),
        );
    }
}
