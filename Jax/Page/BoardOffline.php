<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;
use Jax\Interfaces\Route;
use Jax\Page;
use Jax\Request;
use Jax\Template;

use function nl2br;

final readonly class BoardOffline implements Route
{
    public function __construct(
        private Config $config,
        private Page $page,
        private Request $request,
        private Template $template,
    ) {}

    public function route($params): void
    {
        if ($this->request->isJSUpdate()) {
            return;
        }

        $this->page->append(
            'PAGE',
            $this->template->meta(
                'box',
                '',
                'Error',
                $this->page->error(
                    "You don't have permission to view the board. "
                    . 'If you have an account that has permission, '
                    . 'please log in.'
                    . ($this->config->getSetting('boardoffline') && $this->config->getSetting('offlinetext')
                    ? '<br /><br />Note:<br />' . nl2br((string) $this->config->getSetting('offlinetext'), false)
                    : ''),
                ),
            ),
        );
    }
}
