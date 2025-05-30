<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class Message extends Model
{
    public const TABLE = 'messages';

    public const FIELDS = [
        'id',
        'to',
        'from',
        'title',
        'message',
        'read',
        'date',
        'deletedRecipient',
        'deletedSender',
        'flag',
    ];

    public int $id = 0;

    public ?int $to = null;

    public ?int $from = null;

    public string $title = '';

    public string $message = '';

    public int $read = 0;

    public ?string $date = null;

    public int $deletedRecipient = 0;

    public int $deletedSender = 0;

    public int $flag = 0;
}
