<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;
use Jax\Attributes\Column;
use Jax\Constants\Groups;
use Jax\Models\Group;
use Jax\Models\Member;
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
#[UsesClass(Model::class)]
#[UsesClass(Column::class)]
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
                'select',
            ])
            ->getMock()
        ;

        $database->expects(self::never())
            ->method('select')
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

        $group = new Group();
        $group->canAttach = 1;
        $group->canPoll = 1;
        $group->canPost = 1;
        $group->canCreateTopics = 1;

        $userMember = new Member();
        $userMember->groupID = Groups::Admin->value;

        $user = new User(
            $database,
            $container->get(Jax::class),
            $container->get(IPAddress::class),
            $userMember,
            $group,
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
                'select',
            ])
            ->getMock()
        ;

        $database->expects(self::never())
            ->method('select')
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

        $group = new Group();
        $group->canPost = 1;

        $userMember = new Member();
        $userMember->groupID = Groups::Guest->value;

        $user = new User(
            $database,
            $container->get(Jax::class),
            $container->get(IPAddress::class),
            $userMember,
            $group,
        );

        self::assertSame(
            $this->decoded[Groups::Guest->value],
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
                'select',
            ])
            ->getMock()
        ;

        $database->expects(self::never())
            ->method('select')
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

        $group = new Group();
        $group->canPost = 1;

        $userMember = new Member();
        $userMember->groupID = Groups::Banned->value;

        $user = new User(
            $database,
            $container->get(Jax::class),
            $container->get(IPAddress::class),
            $userMember,
            $group,
        );

        $expected = $this->decoded[Groups::Banned->value];
        $result = $user->getForumPerms($this->encodedForumFlags);
        self::assertSame($expected, $result);
    }
}
