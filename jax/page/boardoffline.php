<?php

declare(strict_types=1);

namespace Jax\Page;

use Jax\Config;

use function nl2br;

final class BoardOffline
{
    public function __construct(\Jax\Config $config) {
        $this->config = $config;
    }

    public function route(): void
    {
        global $PAGE,$JAX;
        if ($PAGE->jsupdate) {
            return;
        }

        $PAGE->append(
            'PAGE',
            $PAGE->meta(
                'box',
                '',
                'Error',
                $PAGE->error(
                    "You don't have permission to view the board. "
                    . 'If you have an account that has permission, '
                    . 'please log in.'
                    . ($this->config->getSetting('boardoffline') && $this->config->getSetting('offlinetext')
                    ? '<br /><br />Note:<br />' . nl2br((string) $this->config->getSetting('offlinetext'))
                    : ''),
                ),
            ),
        );
    }
}
