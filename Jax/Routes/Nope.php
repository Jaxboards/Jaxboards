<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\Interfaces\Route;
use Jax\Page;
use Override;

final readonly class Nope implements Route
{
    public function __construct(
        private Page $page,
    ) {}

    #[Override]
    public function route($params): void
    {
        $this->page->command('script', <<<'JS'
            (function() {
                const img = Object.assign(new Image(), { src: '/Script/eggs/nope.png'});
                Object.assign(img.style, {
                    position: 'fixed',
                });

                img.animate(
                    [
                        { bottom: '-311px', right: 0 },
                        { bottom: 0, right: 0 },
                        { bottom: 0, right: 0, offset: .5},
                        { bottom: 0, right: 0, offset: .7},
                        { bottom: 0, right: '100%', offset: 1 }
                    ],
                    { duration: 5000, fill: 'forwards' }
                );

                document.body.append(img);

            })();
            JS);
        $this->page->command('playsound', 'nope.avi');
        $this->page->command('preventNavigation');
    }
}
