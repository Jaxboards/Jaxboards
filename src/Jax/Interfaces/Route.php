<?php

declare(strict_types=1);

namespace Jax\Interfaces;

interface Route
{
    /**
     * @param array<string,string> $params
     */
    public function route(array $params): void;
}
