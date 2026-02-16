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
            'migrate' => Migrations::class,
            'mago-to-sonar' => MagoToSonar::class,
            'greater-version' => GreaterVersion::class,
            'composer-version' => ComposerVersion::class,
            'help' => Help::class,
        ];
    }
}
