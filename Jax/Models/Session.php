<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Session extends Model
{
    public const TABLE = 'session';

    #[PrimaryKey]
    #[Column(name: 'id', type: 'string', length: 191, nullable: false)]
    public string $id = '';

    #[Column(name: 'uid', type: 'int', unsigned: true)]
    public ?int $uid = null;

    #[Column(name: 'ip', type: 'binary', length: 16, nullable: false, default: '')]
    public string $ip = '';

    #[Column(name: 'vars', type: 'text', nullable: false, default: '')]
    public string $vars = '';

    #[Column(name: 'lastUpdate', type: 'datetime')]
    public ?string $lastUpdate = null;

    #[Column(name: 'lastAction', type: 'datetime')]
    public ?string $lastAction = null;

    #[Column(name: 'runonce', type: 'text', nullable: false, default: '')]
    public string $runonce = '';

    #[Column(name: 'location', type: 'text', nullable: false, default: '')]
    public string $location = '';

    #[Column(name: 'usersOnlineCache', type: 'text', nullable: false, default: '')]
    public string $usersOnlineCache = '';

    #[Column(name: 'isBot', type: 'bool')]
    public int $isBot = 0;

    #[Column(name: 'buddyListCache', type: 'text', nullable: false, default: '')]
    public string $buddyListCache = '';

    #[Column(name: 'locationVerbose', type: 'string', length: 100, nullable: false, default: '')]
    public string $locationVerbose = '';

    #[Column(name: 'useragent', type: 'text', nullable: false, default: '')]
    public string $useragent = '';

    #[Column(name: 'forumsread', type: 'json', nullable: false, default: '{}')]
    public string $forumsread = '{}';

    #[Column(name: 'topicsread', type: 'json', nullable: false, default: '{}')]
    public string $topicsread = '{}';

    #[Column(name: 'readDate', type: 'datetime')]
    public ?string $readDate = null;

    #[Column(name: 'hide', type: 'bool')]
    public int $hide = 0;
}
