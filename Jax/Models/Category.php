<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Category extends Model
{
    public const TABLE = 'categories';

    public const FIELDS = [
        'id',
        'title',
        'order',
    ];

    #[PrimaryKey]
    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false, autoIncrement: true)]
    public int $id = 0;

    #[Column(name: 'title', type: 'string', lenght: 255, nullable: false)]
    public string $title = '';

    #[Column(name: 'order', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $order = 0;
}
