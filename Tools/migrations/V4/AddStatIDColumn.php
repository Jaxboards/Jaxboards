<?php

declare(strict_types=1);

namespace tools\migrations\V4;

use Jax\Database;

final class AddStatIDColumn
{
    public function execute(Database $database): void
    {
        $database->special(
            'ALTER TABLE %t ADD `id` INT UNSIGNED NOT NULL FIRST, ADD PRIMARY KEY (`id`)',
            ['stats'],
        );

        $database->special('UPDATE %t SET id = 1', ['stats']);
    }
}
