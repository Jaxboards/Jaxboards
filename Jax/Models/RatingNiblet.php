<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class RatingNiblet extends Model
{
    public const TABLE = 'ratingniblets';

    #[PrimaryKey]
    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false, autoIncrement: true)]
    public int $id = 0;

    #[Column(name: 'img', type: 'string', length: 255, nullable: false)]
    public string $img = '';

    #[Column(name: 'title', type: 'string', length: 50, nullable: false)]
    public string $title = '';
}
