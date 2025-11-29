<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Date;
use Jax\Models\Badge;
use Jax\Models\BadgeAssociation;
use Jax\Models\Member;
use Jax\Page;
use Jax\Request;
use Jax\TextFormatting;

use function array_key_exists;

final readonly class Badges
{
    public function __construct(
        private Config $config,
        private Date $date,
        private Page $page,
        private Request $request,
        private TextFormatting $textFormatting,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) $this->config->getSetting('badgesEnabled');
    }

    public function render(): void
    {
        $badgeId = (int) $this->request->asString->get('badgeId');
        if ($badgeId === 0) {
            return;
        }

        $this->renderBadgeRecipients($badgeId);
    }

    /**
     * @param array<int> $userIds
     *
     * @return array<array<object{badge:Badge,badgeAssociation:BadgeAssociation}>>
     */
    public function fetchBadges(array $userIds): array
    {
        $badgeAssociations = BadgeAssociation::selectMany(
            'WHERE user IN ?',
            $userIds,
        );

        $badges = Badge::joinedOn(
            $badgeAssociations,
            static fn(BadgeAssociation $badgeAssociation): int => $badgeAssociation->badge,
        );

        $badgesPerUser = [];
        foreach ($badgeAssociations as $badgeAssociation) {
            if (!array_key_exists($badgeAssociation->user, $badgesPerUser)) {
                $badgesPerUser[$badgeAssociation->user] = [];
            }

            $badge = $badges[$badgeAssociation->badge];

            $badge->imagePath = $this->textFormatting->blockhtml($badge->imagePath);
            $badge->description = $this->textFormatting->blockhtml($badge->description);

            $badgesPerUser[$badgeAssociation->user][] = (object) [
                'badge' => $badge,
                'badgeAssociation' => $badgeAssociation,
            ];
        }

        return $badgesPerUser;
    }

    public function showTabBadges(Member $member): string
    {
        if (!$this->isEnabled()) {
            $this->page->location("?act=vu{$member->id}");

            return '';
        }

        $badgesPerMember = $this->fetchBadges([$member->id]);

        if (!array_key_exists($member->id, $badgesPerMember)) {
            return 'No badges yet!';
        }

        $badgesHTML = '<div class="badges">';
        foreach ($badgesPerMember[$member->id] as $badgeTuple) {
            $badgesHTML .= <<<HTML
                <section class="badge">
                    <div class="badge-image">
                        <a href="?act=badges&badgeId={$badgeTuple->badge->id}" title="View all users with this badge">
                            <img src='{$badgeTuple->badge->imagePath}' title='{$badgeTuple->badge->badgeTitle}'>
                        </a>
                    </div>
                    <h3 class="badge-title">
                        {$badgeTuple->badge->badgeTitle}
                    </h3>
                    <div class="description">{$badgeTuple->badge->description}</div>
                    <div class="reason">For: {$badgeTuple->badgeAssociation->reason}</div>
                    <div class="award-date">{$this->date->autodate($badgeTuple->badgeAssociation->awardDate)}</div>
                </section>
                HTML;
        }

        return $badgesHTML . '</table>';
    }

    public function renderBadgeRecipients(int $badgeId): void
    {
        if ($this->request->isJSUpdate()) {
            return;
        }

        $badge = Badge::selectOne($badgeId);

        if ($badge === null) {
            return;
        }

        $badgeAssociations = BadgeAssociation::selectMany(
            'WHERE `badge`=?',
            $badgeId,
        );

        $membersWithBadges = Member::joinedOn(
            $badgeAssociations,
            static fn(BadgeAssociation $badgeAssociation): int => $badgeAssociation->user,
        );


        $badgesTable = '';
        foreach ($badgeAssociations as $badgeAssociation) {
            $member = $membersWithBadges[$badgeAssociation->user];

            $badgesTable .= <<<HTML
                <tr>
                    <td>
                        <a href="?act=vu{$member->id}"
                            class="user{$member->id} mgroup{$member->groupID}"
                            >{$member->name}</a>
                    </td>
                    <td class="reason">{$badgeAssociation->reason}</td>
                    <td class="award-date">{$this->date->autodate($badgeAssociation->awardDate)}</td>
                </tr>
                HTML;
        }

        $badgesHTML = <<<HTML
            <table class="badges" style="width:100%">
                <tr><th>User</th><th>Reason</th><th>Award Date</th></tr>
                {$badgesTable}
            </table>
            HTML;

        $page = $this->page->collapseBox(
            "Badge: {$badge->badgeTitle}",
            <<<HTML
                <div class="badges">
                    <section class="badge">
                        <div class="badge-image"><img src="{$badge->imagePath}" title="{$badge->badgeTitle}"></div>
                        <div class="badge-title">{$badge->badgeTitle}</div>
                        <div class="description">{$badge->description}</div>
                    </section>
                    {$badgesHTML}
                </div>
                HTML,
        );

        $this->page->setPageTitle("Viewing Recipients of {$badge->badgeTitle} badge");
        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
    }
}
