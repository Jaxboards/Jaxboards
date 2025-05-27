<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class Post extends Model
{
    public const TABLE = 'posts';

    public const FIELDS = [
        'id',
        'auth_id',
        'post',
        'date',
        'showsig',
        'showemotes',
        'tid',
        'newtopic',
        'ip',
        'edit_date',
        'editby',
        'rating'
    ];

    public int $id = 0;

    public ?int $auth_id = null;

    public string $post = '';

    public string $date = '';

    public int $showsig = 1;

    public int $showemotes = 1;

    public int $tid = 0;

    public int $newtopic = 0;

    public string $ip = '';

    public string $edit_date = '';

    public ?int $editby = null;

    public string $rating = '';
}
