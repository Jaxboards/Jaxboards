<?php

namespace Jax\Page\UserProfile;

use Jax\Database;
use Jax\Date;
use Jax\Jax;
use Jax\Page;
use Jax\Request;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

class Comments {

    private ?array $profile = null;

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

    /**
     * @param array<string,int|float|string|null> $profile
     */
    public function render(array $profile): string {
        $this->profile = $profile;

        $tabHTML = '';

        $this->handleCommentDeletion();
        $tabHTML .= $this->handleCommentCreation();

        if (!$this->user->isGuest() && $this->user->getPerm('can_add_comments')) {
            $tabHTML .= $this->template->meta(
                'userprofile-comment-form',
                $this->user->get('name') ?? '',
                $this->user->get('avatar') ?: $this->template->meta('default-avatar'),
                $this->jax->hiddenFormFields(
                    [
                        'act' => 'vu' . $this->profile['id'],
                        'page' => 'comments',
                        'view' => 'profile',
                    ],
                ),
            );
        }

        $comments = $this->fetchComments();

        if (!$comments) {
            return $tabHTML . 'No comments to display!';
        }

        foreach ($comments as $comment) {
            $act = $this->request->both('act');
            $deleteLink = (
                $this->user->getPerm('can_delete_comments')
                && $comment['from'] === $this->user->get('id')
                || $this->user->getPerm('can_moderate')
            ) ? <<<HTML
                <a href="?act={$act}&view=profile&page=comments&del={$comment['id']}" class="delete">[X]</a>
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

    private function fetchComments(): ?array
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
            $this->profile['id'],
        );
        $comments = $this->database->arows($result);
        $this->database->disposeresult($result);
        return $comments;
    }

    private function handleCommentCreation(): string {
        $comment = $this->request->post('comment');
        if (!$comment) {
            return '';
        }

        $error = null;
        if ($this->user->isGuest() || !$this->user->getPerm('can_add_comments')) {
            $error = 'No permission to add comments!';
        }

        if ($error !== null) {
            $this->page->command('error', $error);
            return $this->template->meta('error', $error);
        }

        $this->database->safeinsert(
            'activity',
            [
                'affected_uid' => $this->profile['id'],
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
                'to' => $this->profile['id'],
            ],
        );

        return '';
    }

    private function handleCommentDeletion() {
        $deleteComment = (int) $this->request->both('del');
        if (!$deleteComment) {
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
