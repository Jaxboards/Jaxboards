<?php

declare(strict_types=1);

namespace Jax\Models;

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


    public int $id = 0;

    public ?int $category = null;

    public string $title;

    public string $subtitle = '';

    public ?int $lastPostUser = null;

    public ?string $lastPostDate = null;

    public ?int $lastPostTopic = null;

    public string $lastPostTopicTitle = '';

    public string $path = '';

    public int $showSubForums = 0;

    public string $redirect = '';

    public int $topics = 0;

    public int $posts = 0;

    public int $order = 0;

    public string $perms = '';

    public int $orderby = 0;

    public int $nocount = 0;

    public int $redirects = 0;

    public int $trashcan = 0;

    public string $mods = '';

    public int $showLedBy = 0;
}
