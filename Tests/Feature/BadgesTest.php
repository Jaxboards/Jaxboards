<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\Database;
use Jax\Models\Badge;
use Jax\Models\BadgeAssociation;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class BadgesTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testBadgesInProfileNoBadgesYet(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=vu1&page=badges');

        DOMAssert::assertSelectEquals('#pfbox', 'No badges yet!', 1, $page);
    }

    public function testBadgesInProfileHasBadges(): void
    {
        $this->actingAs('admin');
        $this->awardBadgeToAdmin();

        $page = $this->go('?act=vu1&page=badges');

        DOMAssert::assertSelectCount('.badge .badge-image img[src=imagePath]', 1, $page);
        DOMAssert::assertSelectEquals('.badge .badge-title', 'Badge Title', 1, $page);
        DOMAssert::assertSelectEquals('.badge .description', 'Badge Description', 1, $page);
        DOMAssert::assertSelectRegExp('.badge .reason', '/Award Reason/', 1, $page);
        DOMAssert::assertSelectEquals('.badge .award-date .autodate', 'a minute ago', 1, $page);
    }

    public function testBadgesInTopicView(): void
    {
        $this->actingAs('admin');
        $this->awardBadgeToAdmin();

        $page = $this->go('?act=vt1');

        DOMAssert::assertSelectCount('#pid_1 .badges img[src="imagePath"][title="Badge Title"]', 1, $page);
    }

    public function testBadgesListView(): void
    {
        $this->actingAs('admin');
        $this->awardBadgeToAdmin();

        $page = $this->go('?act=badges&badgeId=1');

        DOMAssert::assertSelectCount('.badge-image img[src="imagePath"][title="Badge Title"]', 1, $page);
        DOMAssert::assertSelectEquals('.badge-title', 'Badge Title', 1, $page);
        DOMAssert::assertSelectEquals('.badge .description', 'Badge Description', 1, $page);
        DOMAssert::assertSelectEquals('.badges .user1', 'Admin', 1, $page);
        DOMAssert::assertSelectEquals('.badges .reason', 'Award Reason', 1, $page);
        DOMAssert::assertSelectEquals('.badges .award-date .autodate', 'a minute ago', 1, $page);
    }

    private function awardBadgeToAdmin(): void
    {
        $badge = new Badge();
        $badge->badgeTitle = 'Badge Title';
        $badge->description = 'Badge Description';
        $badge->imagePath = 'imagePath';
        $badge->insert();

        $database = $this->container->get(Database::class);
        $badgeAssociation = new BadgeAssociation();
        $badgeAssociation->awardDate = $database->datetime();
        $badgeAssociation->reason = 'Award Reason';
        $badgeAssociation->user = 1;
        $badgeAssociation->badge = $badge->id;
        $badgeAssociation->insert();
    }
}
