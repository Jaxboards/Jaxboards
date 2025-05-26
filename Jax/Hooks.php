<?php

namespace Jax;

class Hooks {
    /**
     * @var array<string,array<callable>>
     */
    private $hooks = [];

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
            function ($callable) use ($payload) {
                $callable($payload);
            },
            $this->hooks[$hookName]
        );
    }
}

?>
