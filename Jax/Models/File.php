<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class File extends Model
{
    public const TABLE = 'files';

    #[PrimaryKey]
    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false, autoIncrement: true)]
    public int $id = 0;

    #[Column(name: 'name', type: 'string', length: 100, nullable: false)]
    public string $name = '';

    #[Column(name: 'hash', type: 'string', length: 191, nullable: false)]
    public string $hash = '';

    #[Column(name: 'uid', type: 'int', unsigned: true, default: null)]
    public int $uid = 0;

    #[Column(name: 'size', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $size = 0;

    #[Column(name: 'downloads', unsigned: true, nullable: false, default: 0)]
    public int $downloads = 0;

    #[Column('ip', 'binary', length: 16, nullable: false, default: '')]
    public string $ip = '';
}
