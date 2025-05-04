<?php

declare(strict_types=1);

namespace Jax;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_reduce;
use function sort;

final class DebugLog
{
    /**
     * Map of log categories => log lines.
     *
     * @var array<string,array<string>>
     */
    private array $lines = [];

    public function log(string $content, string $category = ''): void
    {
        if (!array_key_exists($category, $this->lines)) {
            $this->lines[$category] = [];
        }

        $this->lines[$category][] = $content;
    }

    /**
     * Returns the full log, with category separation.
     *
     * @return array<string>
     */
    public function getLog(): array
    {
        $categories = array_keys($this->lines);
        sort($categories);

        return array_reduce($categories, function ($lines, $category) {
            $heading = $category !== '' && $category !== '0' ? ["---- {$category} ----"] : [];

            return array_merge($lines, $heading, $this->lines[$category], ['']);
        }, []);
    }
}
