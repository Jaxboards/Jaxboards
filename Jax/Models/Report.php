<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class Report extends Model
{
    public const TABLE = 'reports';

    #[Column(name: 'id', type: 'int', nullable: false, autoIncrement: true, unsigned: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'pid', type: 'int', unsigned: true)]
    #[ForeignKey(table: 'posts', field: 'id', onDelete: 'cascade')]
    public int $pid = 0;

    #[Column(name: 'reason', type: 'string', default: 'other', length: 25)]
    public string $reason = '';

    #[Column(name: 'note', type: 'string', default: '', length: 100)]
    public string $note = '';

    #[Column(name: 'reporter', type: 'int', nullable: false, unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'cascade')]
    public int $reporter = 0;

    #[Column(name: 'reportDate', type: 'datetime')]
    #[Key()]
    public string $reportDate = '';

    #[Column(name: 'acknowledger', type: 'int', nullable: true, unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'cascade')]
    public ?int $acknowledger = null;

    #[Column(name: 'acknowledgedDate', type: 'datetime')]
    public ?string $acknowledgedDate = null;
}
