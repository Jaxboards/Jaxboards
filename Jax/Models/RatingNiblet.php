<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class RatingNiblet extends Model
{
    public const TABLE = 'ratingniblets';

    public const FIELDS = [
        'id',
        'img',
        'title',
    ];

    public int $id = 0;

    public string $img = '';

    public string $title = '';
}
