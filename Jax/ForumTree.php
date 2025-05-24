<?php

declare(strict_types=1);

namespace Jax;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

use function array_filter;
use function array_key_exists;
use function array_map;
use function explode;
use function is_array;

final class ForumTree
{
    /**
     * @var array<array<int>|int>
     */
    private array $tree = [];

    /**
     * Given all forum records, generates a full subforum tree (from forum paths)
     * Leaf nodes are forum IDs.
     *
     * @param array<array<string,mixed>> $forums
     */
    public function __construct($forums)
    {
        foreach ($forums as $forum) {
            $this->addForum($forum);
        }
    }

    public function getIterator(): RecursiveIteratorIterator
    {
        return new RecursiveIteratorIterator(new RecursiveArrayIterator($this->tree));
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

            if (!is_array($node[$pathId])) {
                $node[$pathId] = [$node[$pathId]];
            }

            // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference.DisallowedAssigningByReference
            $node = &$node[$pathId];
        }

        $node[] = $forum['id'];
    }
}
