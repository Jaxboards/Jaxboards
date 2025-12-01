<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\Request;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\DOMAssert;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class ShoutboxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testUnauthShout(): void
    {
        $page = $this->go(new Request(
            post: ['shoutbox_shout' => 'test'],
        ));

        DOMAssert::assertSelectEquals('#shoutbox .error', 'You must be logged in to shout!', 1, $page);
    }
}
