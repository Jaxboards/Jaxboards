<?php

declare(strict_types=1);

namespace Tools;

use DI\Container;

final class Index implements CLIRoute
{
    public function __construct(
        private Container $container,
        private Console $console,
    ) {}

    public function route(array $params): void
    {
        $command = $params[1] ?? '';
        $commands = $this->getAllCommands();

        if (array_key_exists($command, $commands)) {
            $this->container->get($commands[$command])->route(array_slice($params, 2));

            exit(0);
        }

        $this->console->log(
            'Invalid command. Available commands are:',
            '- ' . implode(PHP_EOL . '- ', array_keys($commands)),
        );
    }

    /**
     * @return array<class-string>
     */
    public function getAllCommands(): array
    {
        return [
            'composer-version' => ComposerVersion::class,
            'greater-version' => GreaterVersion::class,
            'help' => Help::class,
            'mago-to-sonar' => MagoToSonar::class,
            'migrate' => Migrations::class,
        ];
    }
}
