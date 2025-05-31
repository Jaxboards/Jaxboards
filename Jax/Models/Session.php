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
        'lastUpdate',
        'lastAction',
        'runonce',
        'location',
        'usersOnlineCache',
        'isBot',
        'buddyListCache',
        'locationVerbose',
        'useragent',
        'forumsread',
        'topicsread',
        'readDate',
        'hide',
    ];

    public string $id = '';

    public ?int $uid = null;

    public string $ip = '';

    public string $vars = '';

    public ?string $lastUpdate = null;

    public ?string $lastAction = null;

    public string $runonce = '';

    public string $location = '';

    public string $usersOnlineCache = '';

    public int $isBot = 0;

    public string $buddyListCache = '';

    public string $locationVerbose = '';

    public string $useragent = '';

    public string $forumsread = '{}';

    public string $topicsread = '{}';

    public ?string $readDate = null;

    public int $hide = 0;
}
