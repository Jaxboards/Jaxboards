<?php

declare(strict_types=1);

namespace Jax;

use Generator;

use function array_filter;
use function array_key_exists;
use function array_map;
use function explode;

final class ForumTree
{
    /**
     * @var array<array<int,never>>
     */
    private array $tree = [];

    /**
     * Given all forum records, generates a full subforum tree (from forum paths).
     *
     * @param array<array<string,mixed>> $forums
     */
    public function __construct($forums)
    {
        foreach ($forums as $forum) {
            $this->addForum($forum);
        }
    }

    /**
     * @return array<array<int>|int>
     */
    public function getTree(): array
    {
        return $this->tree;
    }

    public function getIterator(): Generator
    {
        return $this->recurseInto($this->tree);
    }

    /**
     * @param array<mixed> $forums
     */
    private function recurseInto(
        array $forums,
        int $depth = 0,
    ): Generator {
        foreach ($forums as $forumId => $subForums) {
            yield $depth => $forumId;
            if ($subForums === []) {
                continue;
            }

            yield from $this->recurseInto($subForums, $depth + 1);
        }
    }

    /**
     * @param array<string,mixed> $forum
     *
     * @psalm-suppress UnsupportedPropertyReferenceUsage
     */
    private function addForum(array $forum): void
    {
        $path = array_filter(
            array_map(
                static fn($pathId): int => (int) $pathId,
                explode(' ', (string) $forum['path']),
            ),
            static fn($pathId): bool => (bool) $pathId,
        );

        // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference.DisallowedAssigningByReference
        $node = &$this->tree;

        foreach ($path as $pathId) {
            if (!array_key_exists($pathId, $node)) {
                $node[$pathId] = [];
            }

            // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference.DisallowedAssigningByReference
            $node = &$node[$pathId];
        }

        $node[$forum['id']] = [];
    }
}
