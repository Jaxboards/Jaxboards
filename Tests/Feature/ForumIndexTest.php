<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
#[CoversNothing]
final class ForumIndexTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[CoversNothing]
    public function testViewForumIndexAsAdmin(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?');

        DOMAssert::assertSelectEquals('#userbox .welcome', 'Admin', 1, $page);

        DOMAssert::assertSelectEquals('#cat_1 .title', 'Category', 1, $page);

        DOMAssert::assertSelectEquals('#fid_1 .description', 'Your very first forum!', 1, $page);
        DOMAssert::assertSelectEquals('#fid_1_lastpost', 'Welcome to Jaxboards!', 1, $page);

        DOMAssert::assertSelectEquals('#stats .content', '1 User Online:', 1, $page);
        DOMAssert::assertSelectEquals('#statusers .user2', 'Admin', 1, $page);
        DOMAssert::assertSelectEquals('#stats .userstoday', '1 User Online Today:', 1, $page);
    }
}
