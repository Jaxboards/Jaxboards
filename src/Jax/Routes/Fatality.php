<?php

declare(strict_types=1);

namespace Jax\Routes;

use Jax\Interfaces\Route;
use Jax\Page;
use Override;

final readonly class Fatality implements Route
{
    public function __construct(
        private Page $page,
    ) {}

    #[Override]
    public function route($params): void
    {
        $this->page->command('script', <<<'JS'
            (function() {
                const img = Object.assign(new Image(), { src: '/assets/eggs/fatality.gif'});
                const size = { width: 175, height: 25 };
                Object.assign(img.style, {
                    position: 'fixed',
                    left: '50%',
                    translate: '-50% -50%',
                    height: `${2 * size.height}px`,
                    zIndex: 99999
                });

                img.animate(
                    [
                        { top: '0' },
                        { top: '50%' }
                    ],
                    { duration: 5000, fill: 'forwards' }
                );

                setTimeout(() => img.remove(), 6000);

                document.body.append(img);
            })();
        JS);
        $this->page->command('playsound', 'fatality');
        $this->page->command('preventNavigation');
    }
}
