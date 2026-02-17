<?php

declare(strict_types=1);

namespace Tools;

final class Index
{
    /**
     * @return array<class-string>
     */
    public function get_all_commands(): array
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
