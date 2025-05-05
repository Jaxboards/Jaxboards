<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;
use Jax\Constants\Groups;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

use function base64_decode;

/**
 * @internal
 */
#[CoversClass(User::class)]
#[Small]
#[UsesClass(Config::class)]
#[UsesClass(Database::class)]
#[UsesClass(DomainDefinitions::class)]
#[UsesClass(IPAddress::class)]
#[UsesClass(Jax::class)]
#[UsesClass(ServiceConfig::class)]
#[UsesFunction('\Jax\pathjoin')]
final class UserTest extends TestCase
{
    private string $encodedForumFlags;

    // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
    // disable array order because we have to match the output order from the
    // code we're testing
    /**
     * @var array<int,array<string,bool>>
     */
    private array $decoded = [
        Groups::Guest => [
            'upload' => false,
            'reply' => false,
            'start' => false,
            'read' => true,
            'view' => true,
            'poll' => false,
        ],
        Groups::Banned => [
            'upload' => false,
            'reply' => false,
            'start' => false,
            'read' => true,
            'view' => true,
            'poll' => false,
        ],
    ];

    // phpcs:enable

    protected function setUp(): void
    {
        parent::setUp();

        $this->encodedForumFlags = base64_decode('AAEAPgADABgABAAYAAUAGAAGAD8=', true);
    }

    public function testGetForumPermissionAsAdmin(): void
    {
        // admin? where are these defined? these should be constants or an enum
        $database = self::getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'arow',
                'disposeresult',
                'safeselect',
            ])
            ->getMock()
        ;

        $database->expects(self::never())
            ->method('safeselect')
        ;

        $database->expects(self::never())
            ->method('arow')
        ;

        $database->expects(self::never())
            ->method('disposeresult')
        ;

        $container = new Container([
            Database::class => $database,
        ]);

        $user = new User(
            $database,
            $container->get(Jax::class),
            $container->get(IPAddress::class),
            ['group_id' => Groups::Admin],
            [
                'can_attach' => true,
                'can_poll' => true,
                'can_post' => true,
                'can_post_topics' => true,
            ],
        );

        self::assertSame(
            [
                'poll' => true,
                'read' => true,
                'reply' => true,
                'start' => true,
                'upload' => true,
                'view' => true,
            ],
            $user->getForumPerms($this->encodedForumFlags),
        );
    }

    public function testGetForumPermissionAsGuest(): void
    {
        // guest? where are these defined? these should be constants or an enum
        $database = self::getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'arow',
                'disposeresult',
                'safeselect',
            ])
            ->getMock()
        ;

        $database->expects(self::never())
            ->method('safeselect')
        ;

        $database->expects(self::never())
            ->method('arow')
        ;

        $database->expects(self::never())
            ->method('disposeresult')
        ;

        $container = new Container([
            Database::class => $database,
        ]);

        $user = new User(
            $database,
            $container->get(Jax::class),
            $container->get(IPAddress::class),
            ['group_id' => Groups::Guest],
            ['can_post' => true],
        );

        self::assertSame(
            $this->decoded[Groups::Guest],
            $user->getForumPerms($this->encodedForumFlags),
        );
    }

    public function testGetForumPermissionAsBanned(): void
    {
        // banned? where are these defined? these should be constants or an enum
        $database = self::getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'arow',
                'disposeresult',
                'safeselect',
            ])
            ->getMock()
        ;

        $database->expects(self::never())
            ->method('safeselect')
        ;

        $database->expects(self::never())
            ->method('arow')
        ;

        $database->expects(self::never())
            ->method('disposeresult')
        ;

        $container = new Container([
            Database::class => $database,
        ]);

        $user = new User(
            $database,
            $container->get(Jax::class),
            $container->get(IPAddress::class),
            ['can_post' => true],
        );

        $expected = $this->decoded[Groups::Banned];
        $result = $user->getForumPerms($this->encodedForumFlags);
        self::assertSame($expected, $result);
    }
}
