<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Interfaces\Route;
use Jax\Page;

final readonly class Asteroids implements Route
{
    public function __construct(private Page $page) {}

    public function render(): void
    {
        $this->page->command('loadscript', './Script/eggs/asteroids.min.js');
        $this->page->command('softurl');
    }
}
