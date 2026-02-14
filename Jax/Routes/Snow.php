<?php

declare(strict_types=1);

namespace Jax\Routes;

use Override;
use Jax\Interfaces\Route;
use Jax\Page;

use function max;
use function min;

final readonly class Snow implements Route
{
    public function __construct(
        private Page $page,
    ) {}

    #[Override]
    public function route($params): void
    {
        $snowFlakeCount = (int) ($params['snowFlakeCount'] ?? 200);
        $snowFlakeCount = max(0, min(5000, $snowFlakeCount));

        $this->page->command('snow', $snowFlakeCount);
        $this->page->command('preventNavigation');
    }
}
