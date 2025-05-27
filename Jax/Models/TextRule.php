<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class TextRule extends Model
{
    public const TABLE = 'textrules';

    public const FIELDS = [
        'id',
        'type',
        'needle',
        'replacement',
        'enabled'
    ];

    public int $id = 0;
    public string $type = '';
    public string $needle = '';
    public string $replacement = '';
    public int $enabled = 1;
}
