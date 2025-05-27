<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class Skin extends Model
{
    public const TABLE = 'skins';

    public const FIELDS = [
        'id',
        'using',
        'title',
        'custom',
        'default',
        'hidden',
    ];

    public int $id = 0;

    public int $using = 0;

    public string $title = '';

    public int $custom = 1;

    public string $wrapper = '';

    public int $default = 0;

    public int $hidden = 0;
}
