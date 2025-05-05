<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

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
#[UsesFunction('\Jax\pathjoin')]
final class JaxTest extends TestCase
{
    private string $encodedForumFlags;

    private Jax $jax;

    /**
     * @var array<int,array<string,bool>>
     */
    private array $decoded = [
        1 => ['upload' => false, 'reply' => true, 'start' => true, 'read' => true, 'view' => true, 'poll' => true],
        3 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
        4 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
        5 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
        6 => ['upload' => true, 'reply' => true, 'start' => true, 'read' => true, 'view' => true, 'poll' => true],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->encodedForumFlags = base64_decode(
            'AAEAPgADABgABAAYAAUAGAAGAD8=',
            true,
        );

        $container = new Container();

        $this->jax = $container->get(Jax::class);
    }

    public function testGetForumPermissions(): void
    {
        $result = $this->jax->parseForumPerms($this->encodedForumFlags);

        foreach (array_keys($this->decoded) as $groupId) {
            self::assertSame(
                $this->decoded[$groupId],
                $result[$groupId],
            );
        }
    }

    public function testSerializeForumPermissions(): void
    {
        self::assertSame(
            $this->encodedForumFlags,
            $this->jax->serializeForumPerms($this->decoded),
        );
    }

    public function testPagesWorks(): void
    {
        self::assertSame(
            [1, 9, 10, 11, 12, 13, 14, 15, 16, 20],
            $this->jax->pages(20, 13, 10),
        );
    }

    public function testSanity(): void
    {
        self::assertSame(
            $this->encodedForumFlags,
            $this->jax->serializeForumPerms(
                $this->jax->parseForumPerms($this->encodedForumFlags),
            ),
        );
    }
}
