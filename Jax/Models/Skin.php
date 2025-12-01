<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Skin extends Model
{
    public const TABLE = 'skins';

    #[Column(name: 'id', type: 'int', nullable: false, autoIncrement: true, unsigned: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'using', type: 'int', default: 0, nullable: false, unsigned: true)]
    public int $using = 0;

    #[Column(name: 'title', type: 'string', length: 250, nullable: false)]
    public string $title = '';

    #[Column(name: 'custom', type: 'bool', default: true)]
    public int $custom = 1;

    #[Column(name: 'wrapper', type: 'text', default: '', nullable: false)]
    public string $wrapper = '';

    #[Column(name: 'default', type: 'bool')]
    public int $default = 0;

    #[Column(name: 'hidden', type: 'bool')]
    public int $hidden = 0;
}
