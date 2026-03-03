<?php

declare(strict_types=1);

namespace ACP\Routes;

use ACP\Nav;
use ACP\Page;
use Jax\Interfaces\Route;

final readonly class Index implements Route
{
    public function __construct(
        private Nav $nav,
        private Page $page,
    ) {}

    public function route(array $params): void
    {
        $this->page->append('sidebar', $this->page->render('index', [
            'categories' => $this->nav->getCategories()
        ]));
    }
}
