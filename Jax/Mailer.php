<?php

declare(strict_types=1);

namespace Jax;

use function mail;
use function str_replace;

use const PHP_EOL;

final readonly class Mailer
{
    public function __construct(
        private Config $config,
        private Router $router,
    ) {}

    public function mail(string $email, string $topic, string $message): bool
    {
        $boardname = $this->config->getSetting('boardname') ?: 'JaxBoards';
        $boardurl = $this->router->getRootURL();
        $boardlink = "<a href='{$boardurl}'>{$boardname}</a>";

        return @mail(
            $email,
            $boardname . ' - ' . $topic,
            str_replace(['{BOARDNAME}', '{BOARDURL}', '{BOARDLINK}'], [$boardname, $boardurl, $boardlink], $message),
            'MIME-Version: 1.0'
            . PHP_EOL
            . 'Content-type:text/html;charset=iso-8859-1'
            . PHP_EOL
            . 'From: '
            . $this->config->getSetting('mail_from')
            . PHP_EOL,
        );
    }
}
