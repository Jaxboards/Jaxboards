<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Page extends Model
{
    public const TABLE = 'pages';

    public const PRIMARY_KEY = 'act';

    #[Column(name: 'act', type: 'string', length: 25, nullable: false)]
    #[PrimaryKey]
    public string $act = '';

    #[Column(name: 'page', type: 'text', nullable: false, default: '')]
    public string $page = '';
}
