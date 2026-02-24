<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\Config;
use Jax\Interfaces\Route;
use Jax\Models\Badge;
use Jax\Models\BadgeAssociation;
use Jax\Models\Member;
use Jax\Page;
use Jax\Request;
use Jax\Router;
use Jax\Template;
use Override;

use function array_key_exists;

final readonly class Badges implements Route
{
    public function __construct(
        private Config $config,
        private Page $page,
        private Request $request,
        private Router $router,
        private Template $template,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) $this->config->getSetting('badgesEnabled');
    }

    #[Override]
    public function route($params): void
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
        $badgeAssociations = BadgeAssociation::selectMany('WHERE user IN ?', $userIds);

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
            $this->router->redirect('profile', ['id' => $member->id]);

            return '';
        }

        $badgesPerMember = $this->fetchBadges([$member->id]);

        if (!array_key_exists($member->id, $badgesPerMember)) {
            return 'No badges yet!';
        }

        return $this->template->render('badges/profile-tab', [
            'rows' => $badgesPerMember[$member->id],
        ]);
    }

    public function renderBadgeRecipients(int $badgeId): void
    {
        if ($this->request->isJSUpdate()) {
            return;
        }

        $badge = Badge::selectOne($badgeId);

        if ($badge === null) {
            $this->router->redirect('index');

            return;
        }

        $badgeAssociations = BadgeAssociation::selectMany('WHERE `badge`=?', $badgeId);

        $membersWithBadges = Member::joinedOn(
            $badgeAssociations,
            static fn(BadgeAssociation $badgeAssociation): int => $badgeAssociation->user,
        );

        $page = $this->page->collapseBox(
            "Badge: {$badge->badgeTitle}",
            $this->template->render('badges/recipients', [
                'badge' => $badge,
                'badgeAssociations' => $badgeAssociations,
                'membersWithBadges' => $membersWithBadges,
            ]),
            'badges_' . $badge->id,
        );

        $this->page->setPageTitle("Viewing Recipients of {$badge->badgeTitle} badge");
        $this->page->append('PAGE', $page);
        $this->page->command('update', 'page', $page);
    }
}
