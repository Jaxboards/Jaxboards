<?php

declare(strict_types=1);

namespace Tools\Migrations\V5;

use Jax\Database\Database;
use Tools\Migrations\Migration;

final class AddOpenGraphFields implements Migration
{
    #[\Override]
    public function execute(Database $database): void
    {
        $database->special("ALTER TABLE %t ADD `openGraphMetadata` JSON NOT NULL DEFAULT ('{}')", ['posts']);
    }
}
