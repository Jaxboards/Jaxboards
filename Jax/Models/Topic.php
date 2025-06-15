<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Topic extends Model
{
    public const TABLE = 'topics';

    #[PrimaryKey]
    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false, autoIncrement: true)]
    public int $id = 0;

    #[Column(name: 'title', type: 'string', length: 255, nullable: false)]
    public string $title = '';

    #[Column(name: 'subtitle', type: 'string', length: 255, nullable: false)]
    public string $subtitle = '';

    #[Column(name: 'lastPostUser', type: 'int', unsigned: true)]
    public ?int $lastPostUser = null;

    #[Column(name: 'lastPostDate', type: 'datetime')]
    public ?string $lastPostDate = null;

    #[Column(name: 'fid', type: 'int', unsigned: true)]
    public ?int $fid = null;

    #[Column(name: 'author', type: 'int', unsigned: true)]
    public ?int $author = null;

    #[Column(name: 'replies', type: 'int', unsigned: true, default: 0)]
    public int $replies = 0;

    #[Column(name: 'views', type: 'int', unsigned: true, default: 0)]
    public int $views = 0;

    #[Column(name: 'pinned', type: 'bool')]
    public int $pinned = 0;

    #[Column(name: 'pollChoices', type: 'mediumtext', nullable: false, default: '')]
    public string $pollChoices = '';

    #[Column(name: 'pollResults', type: 'mediumtext', nullable: false, default: '')]
    public string $pollResults = '';

    #[Column(name: 'pollQuestion', type: 'string', length: 255, nullable: false, default: '')]
    public string $pollQuestion = '';

    #[Column(name: 'pollType', type: 'string', length: 10, nullable: false, default: '')]
    public string $pollType = '';

    #[Column(name: 'summary', type: 'string', length: 50, nullable: false, default: '')]
    public string $summary = '';

    #[Column(name: 'locked', type: 'bool')]
    public int $locked = 0;

    #[Column(name: 'date', type: 'datetime')]
    public ?string $date = '';

    #[Column(name: 'op', type: 'int', unsigned: true)]
    public ?int $op = null;

    #[Column(name: 'calendarEvent', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $calendarEvent = 0;
}
