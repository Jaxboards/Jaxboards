<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;
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
#[UsesClass(DomainDefinitions::class)]
#[UsesClass(ServiceConfig::class)]
#[UsesFunction('pathjoin')]
final class UserTest extends TestCase
{
    private string $encodedForumFlags;

    private Container $container;

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

        $this->encodedForumFlags = base64_decode('AAEAPgADABgABAAYAAUAGAAGAD8=', true);

        $this->container = new Container();
    }

    public function testGetForumPermissionAsAdmin(): void
    {
        $user = $this->getUser(
            [
                'can_attach' => true,
                'can_poll' => true,
                'can_post' => true,
                'can_post_topics' => true,
            ],
            ['group_id' => 2],
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
        $user = $this->getUser(
            ['can_post' => true],
            ['group_id' => 3],
        );

        self::assertSame(
            $this->decoded[3],
            $user->getForumPerms($this->encodedForumFlags),
        );
    }

    public function testGetForumPermissionAsBanned(): void
    {
        $user = $this->getUser(
            ['can_post' => true],
            ['group_id' => 4],
        );

        $expected = $this->decoded[4];
        $result = $user->getForumPerms($this->encodedForumFlags);
        self::assertSame($expected, $result);
    }

    /**
     * @param null|array<array-key,mixed> $userPerms
     * @param null|array<array-key,mixed> $userData
     */
    private function getUser(?array $userPerms, ?array $userData): User
    {
        $database = self::getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'arow',
                'basicvalue',
                'disposeresult',
                'safeselect',
                'safeupdate',
            ])
            ->getMock()
        ;

        return new User(
            $database,
            $this->container->get(Jax::class),
            // I think this needs to mock the db too?
            $this->container->get(IPAddress::class),
            $userData,
            $userPerms,
        );
    }
}
