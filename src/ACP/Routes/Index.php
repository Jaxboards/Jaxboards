<?php

declare(strict_types=1);

namespace ACP\Routes;

use ACP\Page;
use Jax\Interfaces\Route;

final readonly class Index implements Route
{
    public function __construct(
        private Page $page,
    ) {}

    public function route(array $params): void
    {
        $this->page->append('page', 'hello world');
    }
}
