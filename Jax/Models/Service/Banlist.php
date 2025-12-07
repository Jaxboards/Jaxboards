<?php

declare(strict_types=1);

namespace Jax\Models\Service;

use Jax\Attributes\Column;
use Jax\Attributes\Key;
use Jax\Model;

final class Banlist extends Model
{
    public const TABLE = 'banlist';

    #[Column(name: 'ip', type: 'binary', default: '', length: 16, nullable: false)]
    #[Key(unique: true)]
    public string $ip = '';
}
