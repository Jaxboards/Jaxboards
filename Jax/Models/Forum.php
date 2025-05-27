<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class Forum extends Model
{
    public const TABLE = 'forums';

    public const FIELDS = [
        'id',
        'cat_id',
        'title',
        'subtitle',
        'lp_uid',
        'lp_date',
        'lp_tid',
        'lp_topic',
        'path',
        'show_sub',
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
        'show_ledby',
    ];


    public int $id = 0;

    public ?int $cat_id = null;

    public string $title;

    public string $subtitle = '';

    public ?int $lp_uid = null;

    public ?string $lp_date = null;

    public ?int $lp_tid = null;

    public string $lp_topic = '';

    public string $path = '';

    public int $show_sub = 0;

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

    public int $show_ledby = 0;
}
