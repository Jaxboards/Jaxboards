<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class Topic extends Model
{
    public const TABLE = 'topics';

    public const FIELDS = [
        'id',
        'title',
        'subtitle',
        'lastPostUser',
        'lastPostDate',
        'fid',
        'author',
        'replies',
        'views',
        'pinned',
        'pollChoices',
        'pollResults',
        'pollQuestion',
        'pollType',
        'summary',
        'locked',
        'date',
        'op',
        'calendarEvent',
    ];

    public int $id = 0;

    public string $title = '';

    public string $subtitle = '';

    public ?int $lastPostUser = null;

    public ?string $lastPostDate = null;

    public ?int $fid = null;

    public ?int $author = null;

    public int $replies = 0;

    public int $views = 0;

    public int $pinned = 0;

    public string $pollChoices = '';

    public string $pollResults = '';

    public string $pollQuestion = '';

    public string $pollType = '';

    public string $summary = '';

    public int $locked = 0;

    public string $date = '';

    public ?int $op = null;

    public int $calendarEvent = 0;
}
