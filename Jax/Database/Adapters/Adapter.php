<?php

declare(strict_types=1);

namespace Jax\Database\Adapters;

use Jax\Database\Model;

interface Adapter
{
    public function install(): void;

    public function createTableQueryFromModel(Model $model): string;
}
