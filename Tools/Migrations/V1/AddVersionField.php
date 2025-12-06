<?php

declare(strict_types=1);

namespace Tools\Migrations\V1;

use Jax\Database;

final class AddVersionField
{
    public function execute(Database $database): void
    {
        $database->special(
            'ALTER TABLE %t ADD COLUMN dbVersion int',
            ['stats'],
        );
    }
}
