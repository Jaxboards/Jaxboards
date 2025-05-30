<?php

declare(strict_types=1);

namespace Jax\Page\UserProfile;

use Jax\Database;
use Jax\Date;
use Jax\DomainDefinitions;
use Jax\Models\Member;
use Jax\Request;
use Jax\RSSFeed;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

use function gmdate;

final readonly class Activity
{
    private const ACTIVITY_LIMIT = 30;

    public function __construct(
        private Database $database,
        private Date $date,
        private DomainDefinitions $domainDefinitions,
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
                'title' => $member->display_name . "'s recent activity",
            ],
        );
        foreach ($this->fetchActivities($member->id) as $activity) {
            $activity['name'] = $member->display_name;
            $activity['group_id'] = $member->group_id;
            $parsed = $this->parseActivityRSS($activity);
            $rssFeed->additem(
                [
                    'description' => $parsed['text'],
                    'guid' => $activity['id'],
                    'link' => $this->domainDefinitions->getBoardUrl() . $parsed['link'],
                    'pubDate' => gmdate('r', $activity['date']),
                    'title' => $parsed['text'],
                ],
            );
        }

        $rssFeed->publish();
    }

    /**
     * @param array<string,mixed> $activity
     */
    private function parseActivity(array $activity): string
    {
        $user = $this->template->meta(
            'user-link',
            $activity['uid'],
            $activity['group_id'],
            $this->user->get()->id === $activity['uid'] ? 'You' : $activity['name'],
        );
        $otherguy = $this->template->meta(
            'user-link',
            $activity['aff_id'],
            $activity['aff_group_id'],
            $activity['aff_name'],
        );

        $date = $this->date->smallDate((int) $activity['date']);
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
                $activity['group_id'],
                $activity['arg1'],
            ) . ' is now known as ' . $this->template->meta(
                'user-link',
                $activity['uid'],
                $activity['group_id'],
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
            $activity['name'] = $member->display_name;
            $activity['group_id'] = $member->group_id;
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
                    UNIX_TIMESTAMP(a.`date`) AS `date`,
                    a.`affected_uid` AS `affected_uid`,
                    a.`tid` AS `tid`,
                    a.`pid` AS `pid`,
                    a.`arg2` AS `arg2`,
                    a.`affected_uid` AS `aff_id`,
                    m.`display_name` AS `aff_name`,
                    m.`group_id` AS `aff_group_id`
                FROM %t a
                LEFT JOIN %t m
                    ON a.`affected_uid`=m.`id`
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
