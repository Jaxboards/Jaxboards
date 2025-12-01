<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\Config;
use Jax\DomainDefinitions;
use Jax\Request;
use Jax\ServiceConfig;
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
        // Configure shoutbox to be enabled
        $this->container->set(Config::class, new Config(
            $this->container->get(ServiceConfig::class),
            $this->container->get(DomainDefinitions::class),
            [
                'shoutbox' => true,
            ],
        ));

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
