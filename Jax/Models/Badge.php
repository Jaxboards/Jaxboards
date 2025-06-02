<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class Badge extends Model
{
    public const TABLE = 'badges';

    public const FIELDS = [
        'id',
        'imagePath',
        'badgeTitle',
        'displayOrder',
        'description',
    ];

    public int $id = 0;

    public string $imagePath = '';

    public string $badgeTitle = '';

    public int $displayOrder = 0;

    public string $description = '';
}
