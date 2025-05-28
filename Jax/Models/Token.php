<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class Token extends Model
{
    public const TABLE = 'tokens';

    public const FIELDS = [
        'token',
        'type',
        'uid',
        'expires',
    ];

    public const PRIMARY_KEY = 'token';

    public string $token = '';

    public string $type = 'login';

    public int $uid = 0;

    public string $expires = '';
}
