<?php

declare(strict_types=1);

namespace Tools\Migrations\V1;

use Jax\Database\Database;
use Tools\Migrations\Migration;

final class AddVersionField implements Migration
{
    #[\Override]
    public function execute(Database $database): void
    {
        $database->special('ALTER TABLE %t ADD COLUMN dbVersion int', ['stats']);
    }
}
