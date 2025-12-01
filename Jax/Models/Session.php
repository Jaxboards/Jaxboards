<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Session extends Model
{
    public const TABLE = 'session';

    #[Column(name: 'id', type: 'string', length: 191, nullable: false)]
    #[PrimaryKey]
    public string $id = '';

    #[Column(name: 'uid', type: 'int', unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'cascade')]
    public ?int $uid = null;

    #[Column(name: 'ip', type: 'binary', default: '', length: 16, nullable: false)]
    public string $ip = '';

    #[Column(name: 'vars', type: 'text', default: '', nullable: false)]
    public string $vars = '';

    #[Column(name: 'lastUpdate', type: 'datetime')]
    public ?string $lastUpdate = null;

    #[Column(name: 'lastAction', type: 'datetime')]
    public ?string $lastAction = null;

    #[Column(name: 'runonce', type: 'text', default: '', nullable: false)]
    public string $runonce = '';

    #[Column(name: 'location', type: 'text', default: '', nullable: false)]
    public string $location = '';

    #[Column(name: 'usersOnlineCache', type: 'text', default: '', nullable: false)]
    public string $usersOnlineCache = '';

    #[Column(name: 'isBot', type: 'bool')]
    public int $isBot = 0;

    #[Column(name: 'buddyListCache', type: 'text', default: '', nullable: false)]
    public string $buddyListCache = '';

    #[Column(name: 'locationVerbose', type: 'string', default: '', length: 100, nullable: false)]
    public string $locationVerbose = '';

    #[Column(name: 'useragent', type: 'text', default: '', nullable: false)]
    public string $useragent = '';

    #[Column(name: 'forumsread', type: 'json', default: '{}', nullable: false)]
    public string $forumsread = '{}';

    #[Column(name: 'topicsread', type: 'json', default: '{}', nullable: false)]
    public string $topicsread = '{}';

    #[Column(name: 'readDate', type: 'datetime')]
    public ?string $readDate = null;

    #[Column(name: 'hide', type: 'bool')]
    public int $hide = 0;
}
