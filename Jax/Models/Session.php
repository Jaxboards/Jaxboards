<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class Session extends Model
{
    public const TABLE = 'session';

    public const FIELDS = [
        'id',
        'uid',
        'ip',
        'vars',
        'last_update',
        'last_action',
        'runonce',
        'location',
        'users_online_cache',
        'is_bot',
        'buddy_list_cache',
        'location_verbose',
        'useragent',
        'forumsread',
        'topicsread',
        'read_date',
        'hide',
    ];

    public string $id = '';

    public ?int $uid = null;

    public string $ip = '';

    public string $vars = '';

    public ?string $last_update = null;

    public ?string $last_action = null;

    public string $runonce = '';

    public string $location = '';

    public string $users_online_cache = '';

    public int $is_bot = 0;

    public string $buddy_list_cache = '';

    public string $location_verbose = '';

    public string $useragent = '';

    public string $forumsread = '{}';

    public string $topicsread = '{}';

    public ?string $read_date = null;

    public int $hide = 0;
}
