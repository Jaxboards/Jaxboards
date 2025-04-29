<?php

declare(strict_types=1);

namespace Jax;

final class DebugLog
{
    /**
     * @var array<string>
     */
    private array $lines;

    public function log(string $content): void
    {
        $this->lines[] = $content;
    }

    /**
     * @return array<string>
     */
    public function getLog(): array
    {
        return $this->lines;
    }
}
