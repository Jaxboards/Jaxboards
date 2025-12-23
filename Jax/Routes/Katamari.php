<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\Interfaces\Route;
use Jax\Page;

final readonly class Katamari implements Route
{
    public function __construct(private Page $page) {}

    public function route($params): void
    {
        $this->page->command('loadscript', '/Script/eggs/katamari.js');
        $this->page->command('softurl');
    }
}
