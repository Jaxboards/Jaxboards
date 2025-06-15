<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Activity extends Model
{
    public const TABLE = 'activity';

    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false, autoIncrement: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'type', type: 'string', length: 20, nullable: false)]
    public string $type = '';

    #[Column(name: 'arg1', type: 'string', length: 255, nullable: false)]
    public string $arg1 = '';

    #[Column(name: 'uid', type: 'int', unsigned: true, nullable: false)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'cascade')]
    public int $uid = 0;

    #[Column(name: 'date', type: 'datetime', default: null)]
    public ?string $date = null;

    #[Column(name: 'affectedUser', type: 'int', unsigned: true, default: null)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'cascade')]
    public ?int $affectedUser = null;

    #[Column(name: 'tid', type: 'int', unsigned: true, default: null)]
    #[ForeignKey(table: 'topics', field: 'id', onDelete: 'cascade')]
    public ?int $tid = 0;

    #[Column(name: 'pid', type: 'int', unsigned: true, default: null)]
    #[ForeignKey(table: 'posts', field: 'id', onDelete: 'cascade')]
    public ?int $pid = 0;

    #[Column(name: 'arg2', type: 'string', length: 255, nullable: false, default: '')]
    public string $arg2 = '';
}
