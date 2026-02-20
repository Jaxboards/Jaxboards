<?php

namespace Tools;

class Console
{
    public function success(string $message): void
    {
        echo "\033[32m{$message}\033[0m" . PHP_EOL;
    }

    public function error(string $message): void
    {
        fwrite(STDERR, "\033[31m{$message}\033[0m");
    }

    public function notice(string $message): void
    {
        echo "\033[33m{$message}\033[0m" . PHP_EOL;
    }

    public function log(string $message): void
    {
        echo $message . PHP_EOL;
    }
}
