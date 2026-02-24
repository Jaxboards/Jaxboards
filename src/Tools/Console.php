<?php

declare(strict_types=1);

namespace Tools;

use const PHP_EOL;

class Console
{
    public function success(string $message): void
    {
        $this->log("\033[32m{$message}\033[0m");
    }

    public function error(string $message): void
    {
        fwrite(STDERR, "\033[31m{$message}\033[0m");
    }

    public function notice(string $message): void
    {
        $this->log("\033[33m{$message}\033[0m");
    }

    public function log(string ...$lines): void
    {
        foreach ($lines as $line) {
            echo $line . PHP_EOL;
        }
    }
}
