<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class ProfileComment extends Model
{
    public const TABLE = 'profile_comments';

    public const FIELDS = [
        'id',
        'to',
        'from',
        'comment',
        'date',
    ];

    #[PrimaryKey]
    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false, autoIncrement: true)]
    public int $id = 0;

    #[Column(name: 'to', type: 'int', unsigned: true, nullable: false)]
    public int $to = 0;

    #[Column(name: 'from', type: 'int', unsigned: true, nullable: false)]
    public int $from = 0;

    #[Column(name: 'comment', type: 'text', nullable: false)]
    public string $comment = '';

    #[Column(name: 'date', type: 'datetime')]
    public ?string $date = null;
}
