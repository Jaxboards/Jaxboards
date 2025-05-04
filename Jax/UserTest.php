<?php

declare(strict_types=1);

namespace Jax;

use DI\Container;

use function base64_decode;

final class UserTest
{
    private readonly string $encodedForumFlags;

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

    public function __construct(
        private readonly Assert $assert,
        private readonly Container $container,
    ) {
        $this->encodedForumFlags = base64_decode('AAEAPgADABgABAAYAAUAGAAGAD8=', true);
    }

    public function getForumPermissionAsAdmin(): void
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

        $expected = [
            'poll' => true,
            'read' => true,
            'reply' => true,
            'start' => true,
            'upload' => true,
            'view' => true,
        ];
        $result = $user->getForumPerms($this->encodedForumFlags);
        $this->assert->deepEquals($expected, $result);
    }

    public function getForumPermissionAsGuest(): void
    {
        $user = $this->getUser(
            ['can_post' => true],
            ['group_id' => 3],
        );

        $expected = $this->decoded[3];
        $result = $user->getForumPerms($this->encodedForumFlags);
        $this->assert->deepEquals($expected, $result);
    }

    public function getForumPermissionAsBanned(): void
    {
        $user = $this->getUser(
            ['can_post' => true],
            ['group_id' => 4],
        );

        $expected = $this->decoded[4];
        $result = $user->getForumPerms($this->encodedForumFlags);
        $this->assert->deepEquals($expected, $result);
    }

    /**
     * @param null|array<array-key,mixed> $userPerms
     * @param null|array<array-key,mixed> $userData
     */
    private function getUser(array $userPerms, array $userData): User
    {
        return new User(
            $this->container->get(Database::class),
            $this->container->get(Jax::class),
            $this->container->get(IPAddress::class),
            $userData,
            $userPerms,
        );
    }
}
