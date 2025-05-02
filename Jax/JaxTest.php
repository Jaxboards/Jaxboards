<?php

declare(strict_types=1);

namespace Jax;

use function array_diff;
use function array_keys;
use function assert;
use function base64_decode;
use function json_encode;

final class JaxTest
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

    public function __construct(
        private Assert $assert,
        private Jax $jax
    )
    {
        $this->encodedForumFlags = base64_decode('AAEAPgADABgABAAYAAUAGAAGAD8=', true);
    }

    public function getForumPermissions(): void
    {
        $result = $this->jax->parseForumPerms($this->encodedForumFlags);

        foreach (array_keys($this->decoded) as $groupId) {
            $this->assert->deepEquals($this->decoded[$groupId], $result[$groupId]);
        }
    }

    public function serializeForumPermissions(): void
    {
        $result = $this->jax->serializeForumPerms($this->decoded);

        $this->assert->equals($result, $this->encodedForumFlags);
    }

    public function pagesWorks() {
        $result = $this->jax->pages(20, 13, 10);

        $this->assert->deepEquals($result, [1, 9, 10, 11, 12, 13, 14, 15, 16, 20]);

    }

    public function sanity(): void
    {
        $this->assert->equals(
            $this->encodedForumFlags, $this->jax->serializeForumPerms(
                $this->jax->parseForumPerms($this->encodedForumFlags)
            )
        );
    }
}
