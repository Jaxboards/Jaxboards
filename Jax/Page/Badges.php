<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Database;
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
        private Database $database,
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
            $this->database,
            'WHERE user IN ?',
            $userIds,
        );

        $badges = Badge::joinedOn(
            $this->database,
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
                            <img src='{$badgeTuple->badge->imagePath}' title='{$badgeTuple->badge->badgeTitle}'>
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
        $badge = Badge::selectOne($this->database, Database::WHERE_ID_EQUALS, $badgeId);

        if ($badge === null) {
            return;
        }

        $page = $this->page->collapseBox(
            "Badge: {$badge->badgeTitle}",
            <<<HTML
                You are viewing all of the people who have received this badge: <img src="{$badge->imagePath}">
                HTML,
        );

        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
    }
}
