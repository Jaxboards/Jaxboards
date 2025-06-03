<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Database;
use Jax\Models\Badge;
use Jax\Models\BadgeAssociation;
use Jax\TextFormatting;

use function array_key_exists;

final readonly class Badges
{
    public function __construct(
        private Config $config,
        private Database $database,
        private TextFormatting $textFormatting,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) $this->config->getSetting('badgesEnabled');
    }

    /**
     * @param array<Model> $otherModels
     * @return array<array<object{badge:Badge,badgeAssociation:BadgeAssociation}>>
     */
    public function fetchBadges(array $otherModels, callable $getUserId): array
    {
        $badgeAssociations = BadgeAssociation::joinedOn(
            $this->database,
            $otherModels,
            $getUserId,
            'user',
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
}
