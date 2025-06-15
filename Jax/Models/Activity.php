<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Activity extends Model
{
    public const TABLE = 'activity';

    public const FIELDS = [
        'id',
        'type',
        'arg1',
        'uid',
        'date',
        'affectedUser',
        'tid',
        'pid',
        'arg2',
    ];

    #[PrimaryKey]
    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false, autoIncrement: true)]
    public int $id = 0;

    #[Column(name: 'type', type: 'string', length: 20, nullable: false)]
    public string $type = '';

    #[Column(name: 'arg1', type: 'string', length: 255, nullable: false)]
    public string $arg1 = '';

    #[Column(name: 'uid', type: 'int', unsigned: true, nullable: false)]
    public int $uid = 0;

    #[Column(name: 'date', type: 'datetime', default: null)]
    public ?string $date = null;

    #[Column(name: 'affectedUser', type: 'int', unsigned: true, default: null)]
    public ?int $affectedUser = null;

    #[Column(name: 'tid', type: 'int', unsigned: true, default: null)]
    public ?int $tid = 0;

    #[Column(name: 'pid', type: 'int', unsigned: true, default: null)]
    public ?int $pid = 0;

    #[Column(name: 'arg2', type: 'string', length: 255, nullable: false, default: '')]
    public string $arg2 = '';
}
