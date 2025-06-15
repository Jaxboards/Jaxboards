<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
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

    #[PrimaryKey]
    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false, autoIncrement: true)]
    public int $id = 0;

    #[Column(name: 'uid', type: 'int', unsigned: true)]
    public int $uid = 0;

    #[Column(name: 'shout', type: 'string', length: 255, nullable: false)]
    public string $shout = '';

    #[Column(name: 'date', type: 'datetime')]
    public ?string $date = null;

    #[Column(name: 'ip', type: 'binary', length: 16, nullable: false, default: '')]
    public string $ip = '';
}
