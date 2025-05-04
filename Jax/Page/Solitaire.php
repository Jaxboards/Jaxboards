<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Page;

final readonly class Solitaire
{
    public function __construct(private Page $page) {}

    public function render(): void
    {
        $this->page->command('loadscript', './Script/eggs/solitaire.js');
        $this->page->command('softurl');
    }
}
