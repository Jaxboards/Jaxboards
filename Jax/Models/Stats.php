<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Stats extends Model
{
    public const TABLE = 'stats';

    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'posts', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $posts = 0;

    #[Column(name: 'topics', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $topics = 0;

    #[Column(name: 'members', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $members = 0;

    #[Column(name: 'most_members', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $most_members = 0;

    #[Column(name: 'most_members_day', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $most_members_day = 0;

    #[Column(name: 'last_register', type: 'int', unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'null')]
    public int $last_register = 0;

    #[Column(name: 'dbVersion', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $dbVersion = 0;
}
