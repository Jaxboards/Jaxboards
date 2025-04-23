<?php

declare(strict_types=1);

namespace Jax\Page;
use Jax\Page;

final class Asteroids
{
    public function __construct(private readonly Page $page) {}
    public function route(): void
    {
        $this->page->JS('loadscript', './Script/asteroids.min.js');
        $this->page->JS('softurl');
    }
}
