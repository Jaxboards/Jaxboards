<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class Message extends Model
{
    public const string TABLE = 'messages';

    #[Column(name: 'id', type: 'int', nullable: false, autoIncrement: true, unsigned: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'to', type: 'int', unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'null')]
    public ?int $to = null;

    #[Column(name: 'from', type: 'int', unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'null')]
    public ?int $from = null;

    #[Column(name: 'title', type: 'string', length: 255, nullable: false)]
    public string $title = '';

    #[Column(name: 'message', type: 'text', nullable: false)]
    public string $message = '';

    #[Column(name: 'read', type: 'bool')]
    public int $read = 0;

    #[Column(name: 'date', type: 'datetime')]
    public ?string $date = null;

    #[Column(name: 'deletedRecipient', type: 'bool')]
    public int $deletedRecipient = 0;

    #[Column(name: 'deletedSender', type: 'bool')]
    public int $deletedSender = 0;

    #[Column(name: 'flag', type: 'bool')]
    public int $flag = 0;
}
