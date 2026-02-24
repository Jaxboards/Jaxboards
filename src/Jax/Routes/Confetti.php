<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\Interfaces\Route;
use Jax\Page;
use Override;

use function max;
use function min;

final readonly class Confetti implements Route
{
    public function __construct(
        private Page $page,
    ) {}

    #[Override]
    public function route($params): void
    {
        $count = (int) ($params['count'] ?? 1000);
        $count = max(0, min(5000, $count));

        $this->page->command('confetti', $count);
        $this->page->command('playsound', 'malo-mart');
        $this->page->command('preventNavigation');
    }
}
