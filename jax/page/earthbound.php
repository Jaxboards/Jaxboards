<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Page;

/**
 * @psalm-api
 */
final readonly class Earthbound
{
    public function __construct(private Page $page) {}

    public function render(): void
    {
        $this->page->JS('softurl');
        $this->page->JS('loadscript', './Script/earthbound.js');
        $this->page->JS('playsound', 'earthbound', './Sounds/earthboundbattle.mp3');
    }
}
