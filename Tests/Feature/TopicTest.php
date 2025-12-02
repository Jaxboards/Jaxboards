<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

/**
 * @internal
 */
final class TopicTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testViewTopicAsAdmin(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=vt1');

        // Breadcrumbs
        DOMAssert::assertSelectEquals('#path li a', 'Example Forums', 1, $page);
        DOMAssert::assertSelectEquals('#path li a', 'Category', 1, $page);
        DOMAssert::assertSelectEquals('#path li a', 'Forum', 2, $page);
        DOMAssert::assertSelectEquals('#path li a', 'Welcome to Jaxboards!', 1, $page);

        DOMAssert::assertSelectRegExp('#page .box .title', '/Welcome to Jaxboards!, Your support is appreciated./', 1, $page);

        DOMAssert::assertSelectEquals('#pid_1 .username', 'Admin', 1, $page);
        DOMAssert::assertSelectEquals('#pid_1 .signature', 'I like tacos', 1, $page);

        DOMAssert::assertSelectRegExp('#pid_1 .post_content', '/only a matter of time/', 1, $page);

        DOMAssert::assertSelectRegExp('#pid_1 .userstats', '/Status: Online!/', 1, $page);
        DOMAssert::assertSelectRegExp('#pid_1 .userstats', '/Group: Admin/', 1, $page);
        DOMAssert::assertSelectRegExp('#pid_1 .userstats', '/Member: #1/', 1, $page);
    }
}
