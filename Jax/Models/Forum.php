<?php

namespace Jax\Models;

use Jax\Model;

class Forum extends Model {
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


    public int $id;
    public ?int $cat_id;
    public string $title;
    public string $subtitle;
    public ?int $lp_uid;
    public ?string $lp_date;
    public ?int $lp_tid;
    public string $lp_topic;
    public string $path;
    public int $show_sub;
    public string $redirect;
    public int $topics;
    public int $posts;
    public int $order;
    public string $perms;
    public int $orderby;
    public int $nocount;
    public int $redirects;
    public int $trashcan;
    public string $mods;
    public int $show_ledby;
}
