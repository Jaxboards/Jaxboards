<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class BadgeAssociation extends Model
{
    public const TABLE = 'badge_associations';

    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false, autoIncrement: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'user', type: 'int', unsigned: true, nullable: false)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'cascade')]
    public int $user = 0;

    #[Column(name: 'badge', type: 'int', unsigned: true, nullable: false)]
    #[ForeignKey(table: 'badges', field: 'id', onDelete: 'cascade')]
    public int $badge = 0;

    #[Column(name: 'badgeCount', type: 'smallint', nullable: false)]
    public int $badgeCount = 0;

    #[Column(name: 'reason', type: 'string', length: 500)]
    public string $reason = '';

    #[Column(name: 'awardDate', type: 'datetime')]
    public string $awardDate = '';
}
