<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class Shout extends Model
{
    public const TABLE = 'shouts';

    public const FIELDS = [
        'id',
        'uid',
        'shout',
        'date',
        'ip',
    ];

    public int $id = 0;
    public int $uid = 0;
    public string $shout = '';
    public string $date = '';
    public string $ip = '';
}
