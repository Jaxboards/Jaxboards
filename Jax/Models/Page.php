<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class Page extends Model
{
    public const TABLE = 'pages';

    public const FIELDS = [
        'act',
        'page',
    ];

    public const PRIMARY_KEY = 'act';

    public string $act = '';

    public string $page = '';
}
