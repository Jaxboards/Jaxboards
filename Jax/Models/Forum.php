<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Forum extends Model
{
    public const TABLE = 'forums';

    public const FIELDS = [
        'id',
        'category',
        'title',
        'subtitle',
        'lastPostUser',
        'lastPostDate',
        'lastPostTopic',
        'lastPostTopicTitle',
        'path',
        'showSubForums',
        'redirect',
        'topics',
        'posts',
        'order',
        'perms',
        'orderby',
        'nocount',
        'redirects',
        'trashcan',
        'mods',
        'showLedBy',
    ];

    #[PrimaryKey]
    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false, autoIncrement: true)]
    public int $id = 0;

    #[Column(name: 'category', type: 'int', unsigned: true, default: null)]
    public ?int $category = null;

    #[Column(name: 'title', type: 'string', length: 255, nullable: false)]
    public string $title;

    #[Column(name: 'subtitle', type: 'text', nullable: false, default: '')]
    public string $subtitle = '';

    #[Column(name: 'lastPostUser', type: 'int', unsigned: true, default: null)]
    public ?int $lastPostUser = null;

    #[Column(name: 'lastPostDate', type: 'datetime', default: null)]
    public ?string $lastPostDate = null;

    #[Column(name: 'lastPostTopic', type: 'int', default: null)]
    public ?int $lastPostTopic = null;

    #[Column(name: 'lastPostTopicTitle', type: 'string', length: 255, nullable: false, default: '')]
    public string $lastPostTopicTitle = '';

    #[Column(name: 'path', type: 'string', length: 100, nullable: false, default: '')]
    public string $path = '';

    #[Column(name: 'showSubForums', type: 'tinyint', unsigned: true, nullable: false, default: 0)]
    public int $showSubForums = 0;

    #[Column(name: 'redirect', type: 'string', length: 255, nullable: false, default: '')]
    public string $redirect = '';

    #[Column(name: 'topics', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $topics = 0;

    #[Column(name: 'posts', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $posts = 0;

    #[Column(name: 'order', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $order = 0;

    #[Column(name: 'perms', type: 'binary', length: 48, nullable: false, default: '')]
    public string $perms = '';

    #[Column(name: 'orderby', type: 'tinyint', unsigned: true, nullable: false, default: 0)]
    public int $orderby = 0;

    #[Column(name: 'nocount', type: 'bool')]
    public int $nocount = 0;

    #[Column(name: 'redirects', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $redirects = 0;

    #[Column(name: 'trashcan', type: 'bool')]
    public int $trashcan = 0;

    #[Column(name: 'mods', type: 'string', length: 255, nullable: false, default: '')]
    public string $mods = '';

    #[Column(name: 'showLedBy', type: 'bool')]
    public int $showLedBy = 0;
}
