<?php

namespace Tools;

use ReflectionClass;

/**
 * Displays help text for a command.
 *
 * Usage:
 * - help {command}
 */
class Help implements CLIRoute
{
    public function __construct(
        private Index $index
    ) {}

    public function route(array $params): void
    {
        $commands = $this->index->get_all_commands();
        $command = $params[0] ?? '';

        if (!$command) {
            $this->get_help_text(Help::class);
            return;
        }

        if (!array_key_exists($command, $commands)) {
            echo 'Help: command not found. Available commands are: ' . implode(', ', array_keys($commands));
            return;
        }

        echo $this->get_help_text($commands[$command]);
    }

    private function get_help_text(string $classString): void
    {
        $ref = new ReflectionClass($classString);
        echo preg_replace('/^[\/* ]+/m', '', $ref->getDocComment());
    }
}
