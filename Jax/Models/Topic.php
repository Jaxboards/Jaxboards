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
        'lp_uid',
        'lp_date',
        'fid',
        'auth_id',
        'replies',
        'views',
        'pinned',
        'poll_choices',
        'poll_results',
        'poll_q',
        'poll_type',
        'summary',
        'locked',
        'date',
        'op',
        'cal_event',
    ];

    public int $id = 0;

    public string $title = '';

    public string $subtitle = '';

    public ?int $lp_uid = null;

    public ?string $lp_date = null;

    public ?int $fid = null;

    public ?int $auth_id = null;

    public int $replies = 0;

    public int $views = 0;

    public int $pinned = 0;

    public string $poll_choices = '';

    public string $poll_results = '';

    public string $poll_q = '';

    public string $poll_type = '';

    public string $summary = '';

    public int $locked = 0;

    public string $date = '';

    public ?int $op = null;

    public int $cal_event = 0;
}
