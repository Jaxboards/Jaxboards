<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class Shout extends Model
{
    public const TABLE = 'shouts';

    #[Column(name: 'id', type: 'int', nullable: false, autoIncrement: true, unsigned: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'uid', type: 'int', unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'cascade')]
    public int $uid = 0;

    #[Column(name: 'shout', type: 'string', length: 255, nullable: false)]
    public string $shout = '';

    #[Column(name: 'date', type: 'datetime')]
    public ?string $date = null;

    #[Column(name: 'ip', type: 'binary', default: '', length: 16, nullable: false)]
    #[Key]
    public string $ip = '';
}
