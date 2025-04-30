<?php

declare(strict_types=1);

namespace Jax;

use Jax\User;

class UserTest {
    private string $encodedForumFlags;

    // private array $decoded = [
    //     1 => ['upload' => false, 'reply' => true, 'start' => true, 'read' => true, 'view' => true, 'poll' => true],
    //     3 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
    //     4 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
    //     5 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
    //     6 => ['upload' => true, 'reply' => true, 'start' => true, 'read' => true, 'view' => true, 'poll' => true],
    // ];

    public function __construct(
        private User $user
    ) {
        $this->encodedForumFlags = base64_decode('AAEAPgADABgABAAYAAUAGAAGAD8=');
    }

    public function getForumPermissionAsAdmin() {
        $user = $this->user;

        $user->userPerms = ['can_poll' => true, 'can_post' => true, 'can_post_topics' => true, 'can_attach' => true];
        $user->userData = ['group_id' => 2];

        $expected = ['poll' => true, 'read' => true, 'reply' => true, 'start' => true, 'upload' => true, 'view' => true];
        $result = $user->getForumPerms($this->encodedForumFlags);
        $diff = array_diff_assoc($expected, $result);

        assert($diff === [], 'Expected value differs: ' . json_encode($diff));
    }

    public function getForumPermissionAsGuest() {
        $user = $this->user;

        $user->userPerms = ['can_post' => true];
        $user->userData = ['group_id' => 3];

        $expected = ['poll' => false, 'read' => true, 'reply' => false, 'start' => false, 'upload' => false, 'view' => true];
        $result = $user->getForumPerms($this->encodedForumFlags);
        $diff = array_diff_assoc($expected, $result);

        assert($diff === [], 'Expected value differs: ' . json_encode($diff));
    }

    public function getForumPermissionAsBanned() {
        $user = $this->user;

        $user->userPerms = ['can_post' => true];
        $user->userData = ['group_id' => 4];

        $expected = ['poll' => false, 'read' => true, 'reply' => false, 'start' => false, 'upload' => false, 'view' => true];
        $result = $user->getForumPerms($this->encodedForumFlags);
        $diff = array_diff_assoc($expected, $result);

        assert($diff === [], 'Expected value differs: ' . json_encode($diff));
    }
}
