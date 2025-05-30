<?php

declare(strict_types=1);

namespace Jax\Page\UserProfile;

use Jax\Database;
use Jax\Date;
use Jax\Jax;
use Jax\Models\Activity;
use Jax\Models\Member;
use Jax\Models\ProfileComment;
use Jax\Page;
use Jax\Request;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

final readonly class Comments
{
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

    public function render(Member $member): string
    {
        $tabHTML = '';

        $this->handleCommentDeletion();
        $tabHTML .= $this->handleCommentCreation($member);

        if (
            !$this->user->isGuest()
            && $this->user->getGroup()?->can_add_comments
        ) {
            $tabHTML .= $this->template->meta(
                'userprofile-comment-form',
                $this->user->get()->name ?? '',
                $this->user->get()->avatar ?: $this->template->meta('default-avatar'),
                $this->jax->hiddenFormFields(
                    [
                        'act' => 'vu' . $member->id,
                        'page' => 'comments',
                        'view' => 'profile',
                    ],
                ),
            );
        }

        $comments = ProfileComment::selectMany(
            $this->database,
            "WHERE `to`=?
            ORDER BY id DESC
            LIMIT 10",
            $member->id
        );

        $membersById = Member::joinedOn(
            $this->database,
            $comments,
            static fn(ProfileComment $comment) => $comment->from
        );

        if ($comments === []) {
            return $tabHTML . 'No comments to display!';
        }

        foreach ($comments as $comment) {
            $act = $this->request->asString->both('act');
            $deleteLink = $this->user->getGroup()?->can_delete_comments
                && $comment->from === $this->user->get()->id
                || $this->user->getGroup()?->can_moderate ? <<<HTML
                    <a href="?act={$act}&page=comments&del={$comment->id}" class="delete">[X]</a>
                    HTML
                : '';

            $fromMember = $membersById[$comment->from];
            $tabHTML .= $this->template->meta(
                'userprofile-comment',
                $this->template->meta(
                    'user-link',
                    $fromMember->id,
                    $fromMember->group_id,
                    $fromMember->display_name,
                ),
                $fromMember->avatar ?: $this->template->meta('default-avatar'),
                $this->date->autoDate($comment->date),
                $this->textFormatting->theWorks($comment->comment),
                $deleteLink,
            );
        }

        return $tabHTML;
    }

    private function handleCommentCreation(Member $member): string
    {
        $comment = $this->request->asString->post('comment');
        if (!$comment) {
            return '';
        }

        $error = null;
        if (
            $this->user->isGuest()
            || !$this->user->getGroup()?->can_add_comments
        ) {
            $error = 'No permission to add comments!';
        }

        if ($error !== null) {
            $this->page->command('error', $error);

            return $this->template->meta('error', $error);
        }

        $activity = new Activity();
        $activity->affected_uid = $member->id;
        $activity->date = $this->database->datetime();
        $activity->type = 'profile_comment';
        $activity->uid = $this->user->get()->id;
        $activity->insert($this->database);

        $profileComment = new ProfileComment();
        $profileComment->comment = $comment;
        $profileComment->date = $this->database->datetime();
        $profileComment->from = $this->user->get()->id;
        $profileComment->to = $member->id;
        $profileComment->insert($this->database);

        return '';
    }

    private function handleCommentDeletion(): void
    {
        $deleteComment = (int) $this->request->asString->both('del');
        if ($deleteComment === 0) {
            return;
        }

        // Moderators can delete any comment
        if ($this->user->getGroup()?->can_moderate) {
            $this->database->delete(
                'profile_comments',
                Database::WHERE_ID_EQUALS,
                $deleteComment,
            );

            return;
        }

        if (!$this->user->getGroup()?->can_delete_comments) {
            return;
        }

        // Delete only own comments
        $this->database->delete(
            'profile_comments',
            'WHERE `id`=? AND `from`=?',
            $deleteComment,
            $this->user->get()->id,
        );
    }
}
