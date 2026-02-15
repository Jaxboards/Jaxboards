<?php

declare(strict_types=1);

namespace Tools\Migrations\V4;

use Jax\Database\Database;
use Override;
use Tools\Migrations\Migration;

final class AddStatIDColumn implements Migration
{
    #[Override]
    public function execute(Database $database): void
    {
        $database->special('ALTER TABLE %t ADD `id` INT UNSIGNED NOT NULL FIRST, ADD PRIMARY KEY (`id`)', ['stats']);

        $database->special('UPDATE %t SET id = 1', ['stats']);
    }
}
