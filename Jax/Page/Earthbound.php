<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\DomainDefinitions;
use Jax\Page;

final readonly class Earthbound
{
    public function __construct(
        private Page $page,
        private DomainDefinitions $domainDefinitions,
    ) {}

    public function render(): void
    {
        $this->page->command('softurl');
        $this->page->command('loadscript', './Script/earthbound.js');
        $this->page->command('playsound', 'earthbound', $this->domainDefinitions->getSoundsURL() . '/earthboundbattle.mp3');
    }
}
