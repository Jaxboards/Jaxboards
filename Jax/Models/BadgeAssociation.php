<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class BadgeAssociation extends Model
{
    public const TABLE = 'badge_associations';

    public const FIELDS = [
        'id',
        'user',
        'badge',
        'badgeCount',
        'reason',
        'awardDate',
    ];

    public int $id = 0;

    public int $user = 0;

    public int $badge = 0;

    public int $badgeCount = 0;

    public string $reason = '';

    public string $awardDate = '';
}
