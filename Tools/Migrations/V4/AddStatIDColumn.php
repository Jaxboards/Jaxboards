<?php

declare(strict_types=1);

namespace Tools\Migrations\V4;

use Override;
use Tools\Migrations\Migration;

final class AddStatIDColumn extends Migration
{
    #[Override]
    public function execute(): void
    {
        $this->database->special('ALTER TABLE %t ADD `id` INT UNSIGNED NOT NULL FIRST, ADD PRIMARY KEY (`id`)', [
            'stats',
        ]);

        $this->database->special('UPDATE %t SET id = 1', ['stats']);
    }
}
