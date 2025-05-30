<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class ProfileComment extends Model
{
    public const TABLE = 'profile_comments';

    public const FIELDS = [
        'id',
        'to',
        'from',
        'comment',
        'date',
    ];

    public int $id = 0;

    public int $to = 0;

    public int $from = 0;

    public string $comment = '';

    public ?string $date = null;
}
