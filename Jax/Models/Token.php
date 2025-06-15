<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Model;

final class Token extends Model
{
    public const TABLE = 'tokens';

    public const PRIMARY_KEY = 'token';

    #[Column(name: 'token', type: 'string', length: 191, nullable: false)]
    public string $token = '';

    #[Column(name: 'type', type: 'string', length: 20, nullable: false, default: 'login')]
    public string $type = 'login';

    #[Column(name: 'uid', type: 'int', unsigned: true, nullable: false)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'cascade')]
    public int $uid = 0;

    #[Column(name: 'expires', type: 'datetime', nullable: false)]
    #[Key]
    public string $expires = '';
}
