<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\Interfaces\Route;
use Jax\Page;

final readonly class Spin implements Route
{
    public function __construct(private Page $page) {}

    public function route($params): void
    {
        $this->page->command('script', <<<'JAVASCRIPT'
                document.querySelector('#container').animate(
                    [
                        { transform: 'rotateZ(0turn)' },
                        { transform: 'rotateZ(1turn)' }
                    ],
                    {
                        duration: 1000,
                        iterations: 1,
                    }
                );
            JAVASCRIPT);
        $this->page->command('softurl');
    }
}
