<?php

namespace Jax\Page\UserProfile;

use Jax\Database;
use Jax\Date;
use Jax\DomainDefinitions;
use Jax\Request;
use Jax\RSSFeed;
use Jax\Template;
use Jax\TextFormatting;
use Jax\User;

class Activity {
    private const ACTIVITY_LIMIT = 30;

    /**
     * var array<string,mixed> the profile we are currently viewing.
     */
    private ?array $profile = null;

    public function __construct(
        private Database $database,
        private Date $date,
        private DomainDefinitions $domainDefinitions,
        private Request $request,
        private TextFormatting $textFormatting,
        private Template $template,
        private User $user,
    ) {}

    /**
     * @param array<string,mixed> $activity
     */
    private function parseActivity(array $activity): array|string
    {
        $user = $this->template->meta(
            'user-link',
            $activity['uid'],
            $activity['group_id'],
            $this->user->get('id') === $activity['uid'] ? 'You' : $activity['name'],
        );
        $otherguy = $this->template->meta(
            'user-link',
            $activity['aff_id'],
            $activity['aff_group_id'],
            $activity['aff_name'],
        );

        $text = match ($activity['type']) {
            'profile_comment' => "{$user}  commented on  {$otherguy}'s profile",
            'new_post' => "{$user} posted in topic <a href='?act=vt{$activity['tid']}&findpost={$activity['pid']}'>{$activity['arg1']}</a>, " . $this->date->smallDate($activity['date']),
            'new_topic' => "{$user} created new topic <a href='?act=vt{$activity['tid']}'>{$activity['arg1']}</a>, " . $this->date->smallDate($activity['date']),
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
            ) . ', ' . $this->date->smallDate($activity['date']),
            'buddy_add' => $user . ' made friends with ' . $otherguy,
            default => '',
        };

        return "<div class=\"activity {$activity['type']}\">{$text}</div>";
    }

    /**
     * @param array<string,mixed> $activity
     */
    private function parseActivityRSS(array $activity): array|string
    {
        return match ($activity['type']) {
            'profile_comment' => [
                'link' => $this->textFormatting->blockhtml('?act=vu' . $activity['aff_id']),
                'text' => $activity['name'] . ' commented on '
                . $activity['aff_name'] . "'s profile",
            ],
            'new_post' => [
                'link' => $this->textFormatting->blockhtml('?act=vt' . $activity['tid'] . '&findpost=' . $activity['pid']),
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
        };
    }

    /**
     * @param array<string,mixed> $profile
     */
    public function render(array $profile): string {
        $this->profile = $profile;

        if ($this->request->both('fmt') === 'RSS') {
            $this->renderRSSFeed();

            return '';
        }

        return $this->renderActivitiesPage();
    }

    private function renderActivitiesPage() {
        $tabHTML = '';

        foreach($this->fetchActivities() as $activity) {
            $activity['name'] = $this->profile['display_name'];
            $activity['group_id'] = $this->profile['group_id'];
            $tabHTML .= $this->parseActivity($activity);
        }

        return !$tabHTML
            ? 'This user has yet to do anything noteworthy!'
            : "<a href='./?act=vu{$this->profile['id']}&amp;page=activity&amp;fmt=RSS' class='social rss' "
            . "style='float:right'>RSS</a>{$tabHTML}";
    }

    public function renderRSSFeed() {
        $feed = new RSSFeed(
            [
                'description' => $this->profile['usertitle'],
                'title' => $this->profile['display_name'] . "'s recent activity",
            ],
        );
        foreach($this->fetchActivities() as $activity) {
            $activity['name'] = $this->profile['display_name'];
            $activity['group_id'] = $this->profile['group_id'];
            $data = $this->parseActivityRSS($activity);
            $feed->additem(
                [
                    'description' => $data['text'],
                    'guid' => $activity['id'],
                    'link' => $this->domainDefinitions->getBoardUrl() . $data['link'],
                    'pubDate' => gmdate('r', $activity['date']),
                    'title' => $data['text'],
                ],
            );
        }

        $feed->publish();
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchActivities(): array {
        $result = $this->database->safespecial(
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
            $this->profile['id'],
            self::ACTIVITY_LIMIT,
        );
        $rows = $this->database->arows($result);
        $this->database->disposeresult($result);
        return  $rows ?? [];
    }
}
