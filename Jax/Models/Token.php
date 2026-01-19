<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class Token extends Model
{
    public const string TABLE = 'tokens';

    #[Column(name: 'token', type: 'string', length: 191, nullable: false)]
    #[PrimaryKey]
    public string $token = '';

    #[Column(
        name: 'type',
        type: 'string',
        default: 'login',
        length: 20,
        nullable: false,
    )]
    public string $type = 'login';

    #[Column(name: 'uid', type: 'int', nullable: false, unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'cascade')]
    public int $uid = 0;

    #[Column(name: 'expires', type: 'datetime', nullable: false)]
    #[Key]
    public string $expires = '';
}
