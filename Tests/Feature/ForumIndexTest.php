<?php

declare(strict_types=1);

namespace Tests;

use DI\Container;
use Jax\Config;
use Jax\Constants\Groups;
use Jax\DomainDefinitions;
use Jax\Jax;
use Jax\Model;
use Jax\ServiceConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\DOMAssert;

use function array_keys;
use function base64_decode;

/**
 * @internal
 */
#[CoversClass(Jax::class)]
#[Small]
#[UsesClass(Config::class)]
#[UsesClass(DomainDefinitions::class)]
#[UsesClass(ServiceConfig::class)]
#[UsesClass(Model::class)]
#[UsesFunction('\Jax\pathjoin')]
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
