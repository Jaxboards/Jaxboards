<?php

declare(strict_types=1);

namespace Tools\Migrations;

use Jax\Database\Database;

/**
 * @internal
 */
interface Migration
{
    public function execute(Database $database): void;
}
