<?php

namespace Jax\Routes;

use Jax\Config;
use Jax\Interfaces\Route;
use Jax\Page;

class Manifest implements Route
{
    public function __construct(
        private Config $config,
        private Page $page
    ) {}

    public function route($params): void
    {
        $this->page->earlyFlush(json_encode(
            [
                'name' => $this->config->get()['boardname'] ?? 'Jaxboards',
                'icons' => [[
                    'src' => '/Service/img/jax.svg',
                    'type' => 'image/svg',
                    'sizes' => 'any'
                ]],
                'start_url' => '/',
                'display' => 'standalone',
            ],
            JSON_PRETTY_PRINT
        ));
    }
}
