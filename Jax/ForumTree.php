<?php

namespace Jax;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

class ForumTree {
    public array $graph;

    /**
     * Given all forum records, generates a full subforum tree (from forum paths)
     * Leaf nodes are forum IDs.
     *
     * @param array<array<string,mixed>> $forums
     */
    public function __construct($forums) {
        $this->graph = [];

        foreach($forums as $forum) {
            $this->addForum($forum);
        }
    }

    /**
     * @param array<string,mixed> $forum
     */
    private function addForum($forum)
    {
        $path = array_filter(
            array_map(
                fn($pathId) => (int) $pathId,
                explode(' ', $forum['path'])
            ),
            fn($pathId) => (bool) $pathId,
        );

        $node = &$this->graph;
        foreach ($path as $pathId) {
            if (!array_key_exists($pathId, $node)) {
                $node[$pathId] = [];
            }
            if (!is_array($node[$pathId])) {
                $node[$pathId] = [$node[$pathId]];
            }
            $node = &$node[$pathId];
        }
        $node[] = $forum['id'];
    }

    public function getIterator(): RecursiveIteratorIterator {
        return new RecursiveIteratorIterator(new RecursiveArrayIterator($this->graph));
    }
}
