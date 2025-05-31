<?php

declare(strict_types=1);

namespace Jax\Models;

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

    public int $id = 0;

    public string $type = '';

    public string $arg1 = '';

    public int $uid = 0;

    public ?string $date = null;

    public ?int $affectedUser = null;

    public ?int $tid = 0;

    public ?int $pid = 0;

    public string $arg2 = '';
}
