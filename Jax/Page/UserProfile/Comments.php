<?php

declare(strict_types=1);

namespace Jax\Page\UserProfile;

use Jax\Database\Database;
use Jax\Date;
use Jax\Jax;
use Jax\Models\Activity;
use Jax\Models\Member;
use Jax\Models\ProfileComment;
use Jax\Page;
use Jax\Request;
use Jax\Router;
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
        private Router $router,
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
            && $this->user->getGroup()?->canAddComments
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
            'WHERE `to`=?
            ORDER BY id DESC
            LIMIT 10',
            $member->id,
        );

        $membersById = Member::joinedOn(
            $comments,
            static fn(ProfileComment $profileComment): int => $profileComment->from,
        );

        if ($comments === []) {
            return $tabHTML . 'No comments to display!';
        }

        foreach ($comments as $comment) {
            $act = $this->request->asString->both('act');

            $deleteLink = '';
            if (
                $this->user->getGroup()?->canModerate
                || ($this->user->getGroup()?->canDeleteComments && $comment->from === $this->user->get()->id)
            ) {
                $deleteCommentURL = $this->router->url('profile', [
                    'id' => $member->id,
                    'page' => 'comments',
                    'del' => $comment->id
                ]);
                $deleteLink = "<a href='{$deleteCommentURL}' class='delete'>[X]</a>";
            }

            $fromMember = $membersById[$comment->from];
            $tabHTML .= $this->template->meta(
                'userprofile-comment',
                $this->template->meta(
                    'user-link',
                    $fromMember->id,
                    $fromMember->groupID,
                    $fromMember->displayName,
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
            || !$this->user->getGroup()?->canAddComments
        ) {
            $error = 'No permission to add comments!';
        }

        if ($error !== null) {
            $this->page->command('error', $error);

            return $this->template->meta('error', $error);
        }

        $activity = new Activity();
        $activity->affectedUser = $member->id;
        $activity->date = $this->database->datetime();
        $activity->type = 'profile_comment';
        $activity->uid = $this->user->get()->id;
        $activity->insert();

        $profileComment = new ProfileComment();
        $profileComment->comment = $comment;
        $profileComment->date = $this->database->datetime();
        $profileComment->from = $this->user->get()->id;
        $profileComment->to = $member->id;
        $profileComment->insert();

        return '';
    }

    private function handleCommentDeletion(): void
    {
        $deleteComment = (int) $this->request->asString->both('del');
        if ($deleteComment === 0) {
            return;
        }

        // Moderators can delete any comment
        if ($this->user->getGroup()?->canModerate) {
            $this->database->delete(
                'profile_comments',
                Database::WHERE_ID_EQUALS,
                $deleteComment,
            );

            return;
        }

        if (!$this->user->getGroup()?->canDeleteComments) {
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
