<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Page;

final class Katamari
{
    public function __construct(
        private readonly Page $page
    ){}

    public function route(): void
    {
        $this->page->JS('loadscript', './Script/katamari.js');
        $this->page->JS('softurl');
    }
}
