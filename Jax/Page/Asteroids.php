<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Page;

final readonly class Asteroids
{
    public function __construct(private Page $page) {}

    public function render(): void
    {
        $this->page->command('loadscript', './Script/asteroids.min.js');
        $this->page->command('softurl');
    }
}
