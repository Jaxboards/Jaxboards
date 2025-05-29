<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class Stats extends Model
{
    public const TABLE = 'stats';

    // This table is a single row
    public const PRIMARY_KEY = '';

    public const FIELDS = [
        'posts',
        'topics',
        'members',
        'most_members',
        'most_members_day',
        'last_register',
    ];


    public int $posts = 0;

    public int $topics = 0;

    public int $members = 0;

    public int $most_members = 0;

    public int $most_members_day = 0;

    public int $last_register = 0;
}
