<?php

declare(strict_types=1);

namespace Jax\DatabaseUtils;

use Jax\Model;

interface DatabaseAdapter
{
    public function install(): void;

    public function createTableQueryFromModel(Model $model): string;
}
