<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class ProfileComment extends Model
{
    public const string TABLE = 'profile_comments';

    #[Column(name: 'id', type: 'int', nullable: false, autoIncrement: true, unsigned: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'to', type: 'int', nullable: false, unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'cascade')]
    public int $to = 0;

    #[Column(name: 'from', type: 'int', nullable: false, unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'cascade')]
    public int $from = 0;

    #[Column(name: 'comment', type: 'text', nullable: false)]
    public string $comment = '';

    #[Column(name: 'date', type: 'datetime')]
    public ?string $date = null;
}
