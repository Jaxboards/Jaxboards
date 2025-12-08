<?php

declare(strict_types=1);

namespace Jax\Page\UserProfile;

use Jax\Database;
use Jax\Date;
use Jax\DomainDefinitions;
use Jax\Models\Activity as ModelsActivity;
use Jax\Models\Member;
use Jax\Page;
use Jax\Request;
use Jax\RSSFeed;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

final readonly class Activity
{
    private const ACTIVITY_LIMIT = 30;

    public function __construct(
        private Database $database,
        private Date $date,
        private DomainDefinitions $domainDefinitions,
        private Page $page,
        private Request $request,
        private TextFormatting $textFormatting,
        private Template $template,
        private User $user,
    ) {}

    public function render(Member $member): string
    {

        if ($this->request->both('fmt') === 'RSS') {
            $this->renderRSSFeed($member);

            return '';
        }

        return $this->renderActivitiesPage($member);
    }

    public function renderRSSFeed(Member $member): void
    {
        $boardURL = $this->domainDefinitions->getBoardURL();
        $rssFeed = new RSSFeed(
            [
                'description' => $member->usertitle,
                'link' => "{$boardURL}?act=vu{$member->id}",
                'title' => $member->displayName . "'s recent activity",
            ],
        );
        foreach ($this->fetchActivities($member->id) as [$activity, $affectedUser]) {
            $parsed = $this->parseActivityRSS($activity, $member, $affectedUser);
            $rssFeed->additem(
                [
                    'description' => $parsed['text'],
                    'guid' => $activity->id,
                    'link' => $this->domainDefinitions->getBoardUrl() . $parsed['link'],
                    'pubDate' => $activity->date ? $this->date->datetimeAsCarbon($activity->date)?->format('r') : '',
                    'title' => $parsed['text'],
                ],
            );
        }

        $this->page->earlyFlush($rssFeed->publish());
    }

    private function parseActivity(ModelsActivity $activity, Member $member, ?Member $affectedUser): string
    {
        $user = $this->template->meta(
            'user-link',
            $activity->uid,
            $member->groupID,
            $this->user->get()->id === $activity->uid ? 'You' : $member->displayName,
        );
        $otherguy = $affectedUser ? $this->template->meta(
            'user-link',
            $affectedUser->id,
            $affectedUser->groupID,
            $affectedUser->displayName,
        ) : '';

        $date = $activity->date
            ? $this->date->smallDate($activity->date)
            : '';
        $text = match ($activity->type) {
            'profile_comment' => "{$user}  commented on  {$otherguy}'s profile",
            'new_post' => <<<HTML
                {$user} posted in topic
                <a href="?act=vt{$activity->tid}&findpost={$activity->pid}">{$activity->arg1}</a>
                {$date}
                HTML,
            'new_topic' => <<<HTML
                {$user} created new topic
                <a href="?act=vt{$activity->tid}">{$activity->arg1}</a>
                {$date}
                HTML,
            'profile_name_change' => $this->template->meta(
                'user-link',
                $activity->uid,
                $member->groupID,
                $activity->arg1,
            ) . ' is now known as ' . $this->template->meta(
                'user-link',
                $activity->uid,
                $member->groupID,
                $activity->arg2,
            ) . ', ' . $date,
            'buddy_add' => $user . ' made friends with ' . $otherguy,
            default => '',
        };

        return "<div class=\"activity {$activity->type}\">{$text}</div>";
    }

    /**
     * @return array{link:string,text:string}
     */
    private function parseActivityRSS(ModelsActivity $activity, Member $user, ?Member $affectedUser): array
    {
        return match ($activity->type) {
            'profile_comment' => [
                'link' => $this->textFormatting->blockhtml('?act=vu' . $activity->affectedUser),
                'text' => "{$user->displayName} commented on {$affectedUser->displayName}'s profile",
            ],
            'new_post' => [
                'link' => $this->textFormatting->blockhtml("?act=vt{$activity->tid}&findpost={$activity->pid}"),
                'text' => $user->displayName . ' posted in topic ' . $activity->arg1,
            ],
            'new_topic' => [
                'link' => $this->textFormatting->blockhtml('?act=vt' . $activity->tid),
                'text' => $user->displayName . ' created new topic ' . $activity->arg1,
            ],
            'profile_name_change' => [
                'link' => $this->textFormatting->blockhtml('?act=vu' . $activity->uid),
                'text' => $activity->arg1 . ' is now known as ' . $activity->arg2,
            ],
            'buddy_add' => [
                'link' => $this->textFormatting->blockhtml('?act=vu' . $activity->uid),
                'text' => $user->displayName . ' made friends with ' . $affectedUser->displayName,
            ],
            default => ['link' => '', 'text' => ''],
        };
    }

    private function renderActivitiesPage(Member $member): string
    {
        $tabHTML = '';

        foreach ($this->fetchActivities($member->id) as [$activity, $affectedUser]) {
            $tabHTML .= $this->parseActivity($activity, $member, $affectedUser);
        }

        return $tabHTML !== ''
            ? <<<HTML
                <a href="?act=vu{$member->id}&amp;page=activity&amp;fmt=RSS"
                   target="_blank" class="social" style='float:right'
                >RSS</a>{$tabHTML}
                HTML
            : 'This user has yet to do anything noteworthy!';
    }

    /**
     * @return array<array{ModelsActivity,?Member}>
     */
    private function fetchActivities(int $profileId): array
    {
        $activities = ModelsActivity::selectMany(<<<'SQL'
            WHERE `uid`= ?
            ORDER BY id DESC
            LIMIT ?
            SQL,
            $profileId,
            self::ACTIVITY_LIMIT
        );

        $members = Member::joinedOn(
            $activities,
            fn(ModelsActivity $activity) => $activity->affectedUser,
        );

        return array_map(
            function (ModelsActivity $activity) use ($members) {
                return [
                    $activity,
                    $members[$activity->affectedUser] ?? null,
                ];
            },
            $activities,
        );
    }
}
