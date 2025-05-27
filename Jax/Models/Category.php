<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class Category extends Model
{
    public const TABLE = 'categories';

    public const FIELDS = [
        'id',
        'title',
        'order',
    ];

    public int $id;

    public string $title;

    public int $order;
}
