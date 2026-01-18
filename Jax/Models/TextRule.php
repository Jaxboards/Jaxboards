<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class TextRule extends Model
{
    public const TABLE = 'textrules';

    #[Column(
        name: 'id',
        type: 'int',
        nullable: false,
        autoIncrement: true,
        unsigned: true,
    )]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'type', type: 'string', length: 10, nullable: false)]
    public string $type = '';

    #[Column(name: 'needle', type: 'string', length: 50, nullable: false)]
    public string $needle = '';

    #[Column(
        name: 'replacement',
        type: 'string',
        length: 500,
        nullable: false,
    )]
    public string $replacement = '';

    #[Column(name: 'enabled', type: 'bool', default: true)]
    public int $enabled = 1;
}
