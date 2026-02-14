<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\Interfaces\Route;
use Jax\Page;

final readonly class Spin implements Route
{
    public function __construct(
        private Page $page,
    ) {}

    #[\Override]
    public function route($params): void
    {
        $this->page->command('script', <<<'JAVASCRIPT'
                (function() {
                    const rotate = 'rotate' + ['X','Y','Z'][Date.now() % 3];
                    document.querySelector('#container').animate(
                        [
                            { transform: `${rotate}(0turn)` },
                            { transform: `${rotate}(1turn)` }
                        ],
                        {
                            duration: 1000,
                            iterations: 1,
                        }
                    );
                })();
            JAVASCRIPT);
        $this->page->command('preventNavigation');
    }
}
