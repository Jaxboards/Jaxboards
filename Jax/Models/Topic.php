<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class Topic extends Model
{
    public const string TABLE = 'topics';

    #[Column(name: 'id', type: 'int', nullable: false, autoIncrement: true, unsigned: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'title', type: 'string', length: 255, nullable: false)]
    #[Key(fulltext: true)]
    public string $title = '';

    #[Column(name: 'subtitle', type: 'string', length: 255, nullable: false)]
    public string $subtitle = '';

    #[Column(name: 'lastPostUser', type: 'int', unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'null')]
    public ?int $lastPostUser = null;

    #[Column(name: 'lastPostDate', type: 'datetime')]
    #[Key]
    public ?string $lastPostDate = null;

    #[Column(name: 'fid', type: 'int', unsigned: true)]
    #[ForeignKey(table: 'forums', field: 'id', onDelete: 'cascade')]
    public ?int $fid = null;

    #[Column(name: 'author', type: 'int', unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'null')]
    public ?int $author = null;

    #[Column(name: 'replies', type: 'int', default: 0, unsigned: true)]
    public int $replies = 0;

    #[Column(name: 'views', type: 'int', default: 0, unsigned: true)]
    public int $views = 0;

    #[Column(name: 'pinned', type: 'bool')]
    public int $pinned = 0;

    #[Column(name: 'pollChoices', type: 'mediumtext', default: '', nullable: false)]
    public string $pollChoices = '';

    #[Column(name: 'pollResults', type: 'mediumtext', default: '', nullable: false)]
    public string $pollResults = '';

    #[Column(name: 'pollQuestion', type: 'string', default: '', length: 255, nullable: false)]
    public string $pollQuestion = '';

    #[Column(name: 'pollType', type: 'string', default: '', length: 10, nullable: false)]
    public string $pollType = '';

    #[Column(name: 'summary', type: 'string', default: '', length: 50, nullable: false)]
    public string $summary = '';

    #[Column(name: 'locked', type: 'bool')]
    public int $locked = 0;

    #[Column(name: 'date', type: 'datetime')]
    public ?string $date = '';

    #[Column(name: 'op', type: 'int', unsigned: true)]
    #[ForeignKey(table: 'posts', field: 'id', onDelete: 'null')]
    public ?int $op = null;

    #[Column(name: 'calendarEvent', type: 'int', default: 0, nullable: false, unsigned: true)]
    #[Key]
    public int $calendarEvent = 0;
}
