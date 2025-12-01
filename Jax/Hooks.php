<?php

declare(strict_types=1);

namespace Jax;

use function array_key_exists;
use function array_map;

final class Hooks
{
    /**
     * @var array<string,array<callable>>
     */
    private array $hooks = [];

    /**
     * Add a listener to any given hook.
     */
    public function addListener(string $hookName, callable $callable): void
    {
        if (!array_key_exists($hookName, $this->hooks)) {
            $this->hooks[$hookName] = [];
        }

        $this->hooks[$hookName][] = $callable;
    }

    /**
     * Dispatches an event to all listeners.
     */
    public function dispatch(string $hookName, mixed $payload = null): void
    {
        if (!array_key_exists($hookName, $this->hooks)) {
            return;
        }

        array_map(
            static function (callable $callable) use ($payload): void {
                $callable($payload);
            },
            $this->hooks[$hookName],
        );
    }
}
