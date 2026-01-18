<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class File extends Model
{
    public const TABLE = 'files';

    #[Column(
        name: 'id',
        type: 'int',
        nullable: false,
        autoIncrement: true,
        unsigned: true,
    )]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'name', type: 'string', length: 100, nullable: false)]
    public string $name = '';

    #[Column(name: 'hash', type: 'string', length: 191, nullable: false)]
    #[Key]
    public string $hash = '';

    #[Column(name: 'uid', type: 'int', default: null, unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'null')]
    public int $uid = 0;

    #[Column(
        name: 'size',
        type: 'int',
        default: 0,
        nullable: false,
        unsigned: true,
    )]
    public int $size = 0;

    #[Column(
        name: 'downloads',
        type: 'int',
        default: 0,
        nullable: false,
        unsigned: true,
    )]
    public int $downloads = 0;

    #[Column(
        name: 'ip',
        type: 'binary',
        default: '',
        length: 16,
        nullable: false,
    )]
    #[Key]
    public string $ip = '';
}
