<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\Interfaces\Route;
use Jax\Page;
use Override;

final readonly class Solitaire implements Route
{
    public function __construct(
        private Page $page,
    ) {}

    #[Override]
    public function route($params): void
    {
        $this->page->command('loadscript', '/assets/eggs/solitaire.js');
        $this->page->command('preventNavigation');
    }
}
