<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
final class MembersListTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testViewMembers(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=members');

        // Breadcrumbs
        DOMAssert::assertSelectEquals('#path a', 'Example Forums', 1, $page);
        DOMAssert::assertSelectEquals('#path a', 'Members', 1, $page);

        DOMAssert::assertSelectEquals('#memberlist .title', 'Members', 1, $page);
        DOMAssert::assertSelectEquals('#memberlist tr:nth-child(2) td:nth-child(2) .user1', 'Admin', 1, $page);
        DOMAssert::assertSelectEquals('#memberlist tr:nth-child(2) td:nth-child(3)', '#1', 1, $page);
        DOMAssert::assertSelectEquals('#memberlist tr:nth-child(2) td:nth-child(4)', '0', 1, $page);
        DOMAssert::assertSelectEquals('#memberlist tr:nth-child(2) td:nth-child(5)', 'a minute ago', 1, $page);
    }
}
