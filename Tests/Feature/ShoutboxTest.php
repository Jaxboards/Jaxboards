<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\Config;
use Jax\Request;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

use function DI\autowire;

/**
 * @internal
 */
final class ShoutboxTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        // Configure shoutbox to be enabled
        $this->container->set(
            Config::class,
            autowire()->constructorParameter('boardConfig', ['shoutbox' => true]),
        );

        parent::setUp();
    }

    public function testUnauthShout(): void
    {
        $page = $this->go(new Request(
            post: ['shoutbox_shout' => 'test'],
        ));

        DOMAssert::assertSelectEquals('#shoutbox .error', 'You must be logged in to shout!', 1, $page);
    }

    public function testAuthShout(): void
    {
        $this->actingAs('member');

        $page = $this->go(new Request(
            post: ['shoutbox_shout' => 'hello world!'],
        ));

        DOMAssert::assertSelectEquals('#shoutbox .shouts .shout .user2', 'Member', 1, $page);
        DOMAssert::assertSelectEquals('#shoutbox .shouts .shout', 'hello world!', 1, $page);
    }
}
