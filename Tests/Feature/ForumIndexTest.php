<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\DOMAssert;

/**
 * @internal
 */
#[CoversNothing]
final class ForumIndexTest extends TestCase
{
    // phpcs:enable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[CoversNothing]
    public function testViewForumIndex(): void
    {
        $page = $this->go('?');

        DOMAssert::assertSelectEquals('#cat_1 .title', 'Category', 1, $page);
        DOMAssert::assertSelectEquals('#fid_1 .description', 'Your very first forum!', 1, $page);
        DOMAssert::assertSelectEquals('#fid_1_lastpost', 'Welcome to Jaxboards!', 1, $page);
        DOMAssert::assertSelectEquals('#statusers', '1 guest', 1, $page);
    }
}
