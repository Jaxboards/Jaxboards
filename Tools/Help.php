<?php

declare(strict_types=1);

namespace Tools;

use Override;
use ReflectionClass;
use ReflectionException;

use const PHP_EOL;

/**
 * Displays help text for a command.
 *
 * Usage:
 * - help {command}
 */
final readonly class Help implements CLIRoute
{
    public function __construct(
        private Console $console,
        private Index $index,
    ) {}

    #[Override]
    public function route(array $params): void
    {
        $commands = $this->index->getAllCommands();
        $command = $params[0] ?? '';

        if (!$command) {
            $this->console->log($this->getHelpText(Help::class));

            $this->console->log(
                'Available commands are:',
                '- ' . implode(PHP_EOL . '- ', array_keys($commands)),
            );
            return;
        }

        if (!array_key_exists($command, $commands)) {
            $this->console->log(
                'Help: command not found. Available commands are: ' . implode(', ', array_keys($commands)),
            );
            return;
        }

        $this->console->log($this->getHelpText($commands[$command]));
    }

    /**
     * @param class-string $classString
     */
    private function getHelpText(string $classString): string
    {
        try {
            $reflectionClass = new ReflectionClass($classString);
            $doc = $reflectionClass->getDocComment();
            if (!$doc) {
                return '';
            }

            $helpText = preg_replace('/^[\/* ]+/m', '', $doc);
            return is_string($helpText) ? $helpText : '';
        } catch (ReflectionException $e) {
            $this->console->error($e->getMessage());
        }

        return '';
    }
}
