<?php

declare(strict_types=1);

namespace Jax;

use function array_diff_assoc;
use function assert;
use function base64_decode;
use function json_encode;

final class UserTest
{
    private string $encodedForumFlags;

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

    public function __construct(private User $user)
    {
        $this->encodedForumFlags = base64_decode('AAEAPgADABgABAAYAAUAGAAGAD8=', true);
    }

    private function assertDeepEquals(array $expected, array $result): void
    {
        $this->assertDeepEquals($expected, $result);
    }

    public function getForumPermissionAsAdmin(): void
    {
        $user = $this->user;

        $user->userPerms = [
            'can_attach' => true,
            'can_poll' => true,
            'can_post_topics' => true,
            'can_post' => true,
        ];
        $user->userData = ['group_id' => 2];

        $expected = [
            'poll' => true,
            'read' => true,
            'reply' => true,
            'start' => true,
            'upload' => true,
            'view' => true
        ];
        $result = $user->getForumPerms($this->encodedForumFlags);

    }

    public function getForumPermissionAsGuest(): void
    {
        $user = $this->user;

        $user->userPerms = ['can_post' => true];
        $user->userData = ['group_id' => 3];

        $expected = $this->decoded[3];
        $result = $user->getForumPerms($this->encodedForumFlags);
        $this->assertDeepEquals($expected, $result);
    }

    public function getForumPermissionAsBanned(): void
    {
        $user = $this->user;

        $user->userPerms = ['can_post' => true];
        $user->userData = ['group_id' => 4];

        $expected = $this->decoded[4];
        $result = $user->getForumPerms($this->encodedForumFlags);
        $this->assertDeepEquals($expected, $result);
    }
}
