<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class BadgeAssociation extends Model
{
    public const string TABLE = 'badge_associations';

    #[Column(name: 'id', type: 'int', nullable: false, autoIncrement: true, unsigned: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'user', type: 'int', nullable: false, unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'cascade')]
    public int $user = 0;

    #[Column(name: 'badge', type: 'int', nullable: false, unsigned: true)]
    #[ForeignKey(table: 'badges', field: 'id', onDelete: 'cascade')]
    public int $badge = 0;

    #[Column(name: 'badgeCount', type: 'smallint', nullable: false)]
    public int $badgeCount = 0;

    #[Column(name: 'reason', type: 'string', length: 500)]
    public string $reason = '';

    #[Column(name: 'awardDate', type: 'datetime')]
    public string $awardDate = '';
}
