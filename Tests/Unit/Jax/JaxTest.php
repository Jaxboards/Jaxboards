<?php

declare(strict_types=1);

namespace Tests\Unit\Jax;

use DI\Container;
use Jax\Config;
use Jax\Constants\Groups;
use Jax\Database\Model;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\Jax;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\ServiceConfig;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Small;
use Tests\UnitTestCase;

use function array_keys;
use function base64_decode;

/**
 * @internal
 */
#[CoversClass(Config::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Model::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(ServiceConfig::class)]
#[Small]
final class JaxTest extends UnitTestCase
{
    private string $encodedForumFlags;

    private Jax $jax;

    /**
     * @var array<int,array<string,bool>>
     */
    private array $decoded = [
        Groups::Member->value => [
            'upload' => false,
            'reply' => true,
            'start' => true,
            'read' => true,
            'view' => true,
            'poll' => true,
        ],
        Groups::Guest->value => [
            'upload' => false,
            'reply' => false,
            'start' => false,
            'read' => true,
            'view' => true,
            'poll' => false,
        ],
        Groups::Banned->value => [
            'upload' => false,
            'reply' => false,
            'start' => false,
            'read' => true,
            'view' => true,
            'poll' => false,
        ],
        Groups::Validating->value => [
            'upload' => false,
            'reply' => false,
            'start' => false,
            'read' => true,
            'view' => true,
            'poll' => false,
        ],
        // Custom group
        6 => [
            'upload' => true,
            'reply' => true,
            'start' => true,
            'read' => true,
            'view' => true,
            'poll' => true,
        ],
    ];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->encodedForumFlags = base64_decode('AAEAPgADABgABAAYAAUAGAAGAD8=', true);

        $container = new Container();

        $this->jax = $container->get(Jax::class);
    }

    public function testGetForumPermissions(): void
    {
        $result = $this->jax->parseForumPerms($this->encodedForumFlags);

        foreach (array_keys($this->decoded) as $groupId) {
            static::assertSame($this->decoded[$groupId], $result[$groupId]);
        }
    }

    public function testSerializeForumPermissions(): void
    {
        static::assertSame($this->encodedForumFlags, $this->jax->serializeForumPerms($this->decoded));
    }

    public function testPagesWorks(): void
    {
        static::assertSame([1, 9, 10, 11, 12, 13, 14, 15, 16, 20], $this->jax->pages(20, 13, 10));
    }

    public function testParseForumsSanity(): void
    {
        static::assertSame(
            $this->encodedForumFlags,
            $this->jax->serializeForumPerms($this->jax->parseForumPerms($this->encodedForumFlags)),
        );
    }
}
