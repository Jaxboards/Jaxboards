<?php

declare(strict_types=1);

namespace Jax\Routes;

use Override;
use Jax\Config;
use Jax\Interfaces\Route;
use Jax\Page;

use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final readonly class Manifest implements Route
{
    public function __construct(
        private Config $config,
        private Page $page,
    ) {}

    #[Override]
    public function route($params): void
    {
        $this->page->earlyFlush(
            json_encode(
                [
                    'name' => $this->config->get()['boardname'] ?? 'Jaxboards',
                    'icons' => [[
                        'src' => '/Service/img/jax.svg',
                        'type' => 'image/svg+xml',
                        'sizes' => 'any',
                    ]],
                    'start_url' => '/',
                    'display' => 'standalone',
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ) ?: '',
        );
    }
}
