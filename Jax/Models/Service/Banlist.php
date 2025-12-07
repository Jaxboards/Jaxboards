<?php

declare(strict_types=1);

namespace Jax\Models\Service;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Banlist extends Model
{
    public const TABLE = 'banlist';

    #[PrimaryKey]
    #[Column(name: 'ipAddress', type: 'binary', default: '', length: 16, nullable: false)]
    public string $ipAddress = '';
}
