<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
final class ProfileTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testViewMissingUser(): void
    {
        $page = $this->go('?act=vu5');

        DOMAssert::assertSelectEquals('#page .error', "Sorry, this user doesn't exist.", 1, $page);
    }

    public function testViewUserProfileAsAdmin(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=vu1');

        // Breadcrumbs
        DOMAssert::assertSelectEquals('#path li a', 'Example Forums', 1, $page);
        DOMAssert::assertSelectEquals('#path li a', "Admin's profile", 1, $page);

        DOMAssert::assertSelectEquals('.leftbar .username .moderate', 'Edit', 1, $page);
        DOMAssert::assertSelectEquals('.leftbar .username', 'Admin', 1, $page);

        DOMAssert::assertSelectEquals('#pfbox', 'This user has yet to do anything noteworthy!', 1, $page);
    }

    public function testViewProfileRSSFeed(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=vu1&page=activity&fmt=RSS');

        $this->assertStringContainsString("<title>Admin's recent activity</title>", $page);
        $this->assertStringContainsString('<link>//example.com?act=vu1</link>', $page);
    }
}
