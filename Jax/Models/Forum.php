<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class Forum extends Model
{
    public const TABLE = 'forums';

    #[
        Column(
            name: 'id',
            type: 'int',
            nullable: false,
            autoIncrement: true,
            unsigned: true,
        ),
    ]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'category', type: 'int', default: null, unsigned: true)]
    #[ForeignKey(table: 'categories', field: 'id', onDelete: 'null')]
    public ?int $category = null;

    #[Column(name: 'title', type: 'string', length: 255, nullable: false)]
    public string $title;

    #[Column(name: 'subtitle', type: 'text', default: '', nullable: false)]
    public string $subtitle = '';

    #[Column(name: 'lastPostUser', type: 'int', default: null, unsigned: true)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'null')]
    public ?int $lastPostUser = null;

    #[Column(name: 'lastPostDate', type: 'datetime', default: null)]
    public ?string $lastPostDate = null;

    #[Column(name: 'lastPostTopic', type: 'int', default: null, unsigned: true)]
    #[ForeignKey(table: 'topics', field: 'id', onDelete: 'null')]
    public ?int $lastPostTopic = null;

    #[
        Column(
            name: 'lastPostTopicTitle',
            type: 'string',
            default: '',
            length: 255,
            nullable: false,
        ),
    ]
    public string $lastPostTopicTitle = '';

    #[
        Column(
            name: 'path',
            type: 'string',
            default: '',
            length: 100,
            nullable: false,
        ),
    ]
    public string $path = '';

    #[
        Column(
            name: 'showSubForums',
            type: 'tinyint',
            default: 0,
            nullable: false,
            unsigned: true,
        ),
    ]
    public int $showSubForums = 0;

    #[
        Column(
            name: 'redirect',
            type: 'string',
            default: '',
            length: 255,
            nullable: false,
        ),
    ]
    public string $redirect = '';

    #[
        Column(
            name: 'topics',
            type: 'int',
            default: 0,
            nullable: false,
            unsigned: true,
        ),
    ]
    public int $topics = 0;

    #[
        Column(
            name: 'posts',
            type: 'int',
            default: 0,
            nullable: false,
            unsigned: true,
        ),
    ]
    public int $posts = 0;

    #[
        Column(
            name: 'order',
            type: 'int',
            default: 0,
            nullable: false,
            unsigned: true,
        ),
    ]
    public int $order = 0;

    #[
        Column(
            name: 'perms',
            type: 'binary',
            default: '',
            length: 48,
            nullable: false,
        ),
    ]
    public string $perms = '';

    #[
        Column(
            name: 'orderby',
            type: 'tinyint',
            default: 0,
            nullable: false,
            unsigned: true,
        ),
    ]
    public int $orderby = 0;

    #[Column(name: 'nocount', type: 'bool')]
    public int $nocount = 0;

    #[
        Column(
            name: 'redirects',
            type: 'int',
            default: 0,
            nullable: false,
            unsigned: true,
        ),
    ]
    public int $redirects = 0;

    #[Column(name: 'trashcan', type: 'bool')]
    public int $trashcan = 0;

    #[
        Column(
            name: 'mods',
            type: 'string',
            default: '',
            length: 255,
            nullable: false,
        ),
    ]
    public string $mods = '';

    #[Column(name: 'showLedBy', type: 'bool')]
    public int $showLedBy = 0;

    /**
     * Given a forum ID, recomputes last post information for the forum.
     */
    public static function fixLastPost(int $forumId): void
    {
        $topic = Topic::selectOne(
            'WHERE `fid`=? ORDER BY `lastPostDate` DESC LIMIT 1',
            $forumId,
        );

        $forum = self::selectOne($forumId);

        if ($topic === null || $forum === null) {
            return;
        }

        $forum->lastPostDate = $topic->lastPostDate;
        $forum->lastPostTopic = $topic->id;
        $forum->lastPostTopicTitle = $topic->title;
        $forum->lastPostUser = $topic->lastPostUser;
        $forum->update();
    }
}
