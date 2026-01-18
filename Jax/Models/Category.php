<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class Category extends Model
{
    public const TABLE = 'categories';

    #[Column(
        name: 'id',
        type: 'int',
        nullable: false,
        autoIncrement: true,
        unsigned: true,
    )]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'title', type: 'string', length: 255, nullable: false)]
    public string $title = '';

    #[Column(
        name: 'order',
        type: 'int',
        default: 0,
        nullable: false,
        unsigned: true,
    )]
    public int $order = 0;
}
