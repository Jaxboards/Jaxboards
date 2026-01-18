<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class RatingNiblet extends Model
{
    public const string TABLE = 'ratingniblets';

    #[Column(
        name: 'id',
        type: 'int',
        nullable: false,
        autoIncrement: true,
        unsigned: true,
    )]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'img', type: 'string', length: 255, nullable: false)]
    public string $img = '';

    #[Column(name: 'title', type: 'string', length: 50, nullable: false)]
    public string $title = '';
}
