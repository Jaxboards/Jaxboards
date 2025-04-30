<?php

declare(strict_types=1);

namespace Jax;

use Jax\Jax;

class JaxTest {
    private string $encodedForumFlags;

    private array $decoded = [
        1 => ['upload' => false, 'reply' => true, 'start' => true, 'read' => true, 'view' => true, 'poll' => true],
        3 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
        4 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
        5 => ['upload' => false, 'reply' => false, 'start' => false, 'read' => true, 'view' => true, 'poll' => false],
        6 => ['upload' => true, 'reply' => true, 'start' => true, 'read' => true, 'view' => true, 'poll' => true],
    ];

    public function __construct(
        private Jax $jax
    ) {
        $this->encodedForumFlags = base64_decode('AAEAPgADABgABAAYAAUAGAAGAD8=');
    }

    public function getForumPermissions() {
        $result = $this->jax->parseForumPerms($this->encodedForumFlags);

        foreach (array_keys($this->decoded) as $groupId) {
            $diff = array_diff($this->decoded[$groupId], $result[$groupId]);
            assert($diff === [], "{$groupId} permissions differs: " . json_encode($diff));
        }
    }

    public function serializeForumPermissions() {
        $result = $this->jax->serializeForumPerms($this->decoded);

        assert($result === $this->encodedForumFlags);
    }

    public function sanity() {
        assert($this->encodedForumFlags === $this->jax->serializeForumPerms($this->jax->parseForumPerms($this->encodedForumFlags)));
    }
}
