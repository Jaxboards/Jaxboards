<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\Interfaces\Route;
use Jax\Page;

final readonly class Earthbound implements Route
{
    public function __construct(
        private Page $page,
    ) {}

    #[\Override]
    public function route($params): void
    {
        $this->page->command('preventNavigation');
        $this->page->command('loadscript', '/Script/eggs/earthbound.js');
        $this->page->command('playsound', 'earthboundbattle');
    }
}
