<?php

declare(strict_types=1);

namespace Tools\Migrations;

use Jax\Database\Database;

/**
 * @internal
 */
abstract class Migration
{
    public function __construct(
        protected Database $database,
    ) {}

    public function execute(): void
    {
        error_log('Migration not implemented correctly');
    }
}
