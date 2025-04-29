<?php

namespace Jax;

class DebugLog {
    /**
     * @var array<string>
     */
    private array $lines;

    function log(string $content) {
        $this->lines[] = $content;
    }

    /**
     * @return array<string>
     */
    function getLog(): array
    {
        return $this->lines;
    }
}
