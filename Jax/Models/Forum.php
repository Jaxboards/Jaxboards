<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\PrimaryKey;
use Jax\Database;
use Jax\Model;

final class Forum extends Model
{
    public const TABLE = 'forums';

    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false, autoIncrement: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'category', type: 'int', unsigned: true, default: null)]
    #[ForeignKey(table: 'categories', field: 'id', onDelete: 'null')]
    public ?int $category = null;

    #[Column(name: 'title', type: 'string', length: 255, nullable: false)]
    public string $title;

    #[Column(name: 'subtitle', type: 'text', nullable: false, default: '')]
    public string $subtitle = '';

    #[Column(name: 'lastPostUser', type: 'int', unsigned: true, default: null)]
    #[ForeignKey(table: 'members', field: 'id', onDelete: 'null')]
    public ?int $lastPostUser = null;

    #[Column(name: 'lastPostDate', type: 'datetime', default: null)]
    public ?string $lastPostDate = null;

    #[Column(name: 'lastPostTopic', type: 'int', unsigned: true, default: null)]
    #[ForeignKey(table: 'topics', field: 'id', onDelete: 'null')]
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
