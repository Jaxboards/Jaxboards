<?php

declare(strict_types=1);

namespace Jax\Models\Service;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class Banlist extends Model
{
    public const string TABLE = 'banlist';

    #[Column(
        name: 'ipAddress',
        type: 'binary',
        default: '',
        length: 16,
        nullable: false,
    )]
    #[PrimaryKey]
    public string $ipAddress = '';
}
