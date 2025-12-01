<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Badge extends Model
{
    public const TABLE = 'badges';

    #[Column(name: 'id', type: 'int', nullable: false, autoIncrement: true, unsigned: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'imagePath', type: 'string', length: 255, nullable: false)]
    public string $imagePath = '';

    #[Column(name: 'badgeTitle', type: 'string', length: 128, nullable: false)]
    public string $badgeTitle = '';

    #[Column(name: 'displayOrder', type: 'int', nullable: false)]
    public int $displayOrder = 0;

    #[Column(name: 'description', type: 'string', length: 255)]
    public string $description = '';
}
