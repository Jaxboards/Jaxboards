<?php

declare(strict_types=1);

namespace Jax\Page\UserProfile;

use Jax\Database;
use Jax\Date;
use Jax\DomainDefinitions;
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
        foreach ($this->fetchActivities($member->id) as $activity) {
            $activity['name'] = $member->displayName;
            $activity['groupID'] = $member->groupID;
            $parsed = $this->parseActivityRSS($activity);
            $rssFeed->additem(
                [
                    'description' => $parsed['text'],
                    'guid' => $activity['id'],
                    'link' => $this->domainDefinitions->getBoardUrl() . $parsed['link'],
                    'pubDate' => $this->date->datetimeAsCarbon($activity['date'])?->format('r'),
                    'title' => $parsed['text'],
                ],
            );
        }

        $this->page->earlyFlush($rssFeed->publish());
    }

    /**
     * @param array<string,mixed> $activity
     */
    private function parseActivity(array $activity): string
    {
        $user = $this->template->meta(
            'user-link',
            $activity['uid'],
            $activity['groupID'],
            $this->user->get()->id === $activity['uid'] ? 'You' : $activity['name'],
        );
        $otherguy = $this->template->meta(
            'user-link',
            $activity['aff_id'],
            $activity['aff_groupID'],
            $activity['aff_name'],
        );

        $date = $activity['date'] ? $this->date->smallDate($activity['date']) : '';
        $text = match ($activity['type']) {
            'profile_comment' => "{$user}  commented on  {$otherguy}'s profile",
            'new_post' => <<<HTML
                {$user} posted in topic
                <a href="?act=vt{$activity['tid']}&findpost={$activity['pid']}">{$activity['arg1']}</a>
                {$date}
                HTML,
            'new_topic' => <<<HTML
                {$user} created new topic
                <a href="?act=vt{$activity['tid']}">{$activity['arg1']}</a>
                {$date}
                HTML,
            'profile_name_change' => $this->template->meta(
                'user-link',
                $activity['uid'],
                $activity['groupID'],
                $activity['arg1'],
            ) . ' is now known as ' . $this->template->meta(
                'user-link',
                $activity['uid'],
                $activity['groupID'],
                $activity['arg2'],
            ) . ', ' . $date,
            'buddy_add' => $user . ' made friends with ' . $otherguy,
            default => '',
        };

        return "<div class=\"activity {$activity['type']}\">{$text}</div>";
    }

    /**
     * @param array<string,mixed> $activity
     *
     * @return array{link:string,text:string}
     */
    private function parseActivityRSS(array $activity): array
    {
        return match ($activity['type']) {
            'profile_comment' => [
                'link' => $this->textFormatting->blockhtml('?act=vu' . $activity['aff_id']),
                'text' => "{$activity['name']} commented on {$activity['aff_name']}'s profile",
            ],
            'new_post' => [
                'link' => $this->textFormatting->blockhtml("?act=vt{$activity['tid']}&findpost={$activity['pid']}"),
                'text' => $activity['name'] . ' posted in topic ' . $activity['arg1'],
            ],
            'new_topic' => [
                'link' => $this->textFormatting->blockhtml('?act=vt' . $activity['tid']),
                'text' => $activity['name'] . ' created new topic ' . $activity['arg1'],
            ],
            'profile_name_change' => [
                'link' => $this->textFormatting->blockhtml('?act=vu' . $activity['uid']),
                'text' => $activity['arg1'] . ' is now known as ' . $activity['arg2'],
            ],
            'buddy_add' => [
                'link' => $this->textFormatting->blockhtml('?act=vu' . $activity['uid']),
                'text' => $activity['name'] . ' made friends with ' . $activity['aff_name'],
            ],
            default => ['link' => '', 'text' => ''],
        };
    }

    private function renderActivitiesPage(Member $member): string
    {
        $tabHTML = '';

        foreach ($this->fetchActivities($member->id) as $activity) {
            $activity['name'] = $member->displayName;
            $activity['groupID'] = $member->groupID;
            $tabHTML .= $this->parseActivity($activity);
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
     * @return array<int,array<string,mixed>>
     */
    private function fetchActivities(int $profileId): array
    {
        $result = $this->database->special(
            <<<'SQL'
                SELECT
                    a.`id` AS `id`,
                    a.`type` AS `type`,
                    a.`arg1` AS `arg1`,
                    a.`uid` AS `uid`,
                    a.`date` AS `date`,
                    a.`affectedUser` AS `affectedUser`,
                    a.`tid` AS `tid`,
                    a.`pid` AS `pid`,
                    a.`arg2` AS `arg2`,
                    a.`affectedUser` AS `aff_id`,
                    m.`displayName` AS `aff_name`,
                    m.`groupID` AS `aff_groupID`
                FROM %t a
                LEFT JOIN %t m
                    ON a.`affectedUser`=m.`id`
                WHERE a.`uid`=?
                ORDER BY a.`id` DESC
                LIMIT ?
                SQL,
            ['activity', 'members'],
            $profileId,
            self::ACTIVITY_LIMIT,
        );

        return $this->database->arows($result);
    }
}
