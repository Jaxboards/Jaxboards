<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class File extends Model
{
    public const TABLE = 'files';

    public const FIELDS = [
        'id',
        'name',
        'hash',
        'uid',
        'size',
        'downloads',
        'ip',
    ];

    public int $id = 0;

    public string $name = '';

    public string $hash = '';

    public string $uid = '';

    public int $size = 0;

    public int $downloads = 0;

    public string $ip = '';
}
