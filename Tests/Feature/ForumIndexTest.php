<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ForumIndexTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testViewForumIndexAsAdmin(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=vf1');

        DOMAssert::assertSelectEquals('#fid_1_listing .title', 'Forum', 1, $page, 'Forum title');
        DOMAssert::assertSelectEquals('#fr_1 .topic', 'Welcome to Jaxboards!', 1, $page, 'Topic Title');
        DOMAssert::assertSelectEquals('#fr_1 .topic', 'Your support is appreciated.', 1, $page, 'Topic Description');
        DOMAssert::assertSelectEquals('#fr_1 .item_1 .user1', 'Admin', 1, $page, 'Topic Author');
        DOMAssert::assertSelectEquals('#fr_1 .last_post .autodate', 'a minute ago', 1, $page, 'Last Post Date');
        DOMAssert::assertSelectEquals('#fr_1 .last_post .user1', 'Admin', 1, $page, 'Last Post Author');
    }
}
