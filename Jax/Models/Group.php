<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class Group extends Model
{
    public const TABLE = 'member_groups';

    public const FIELDS = [
        'id',
        'title',
        'can_post',
        'can_edit_posts',
        'can_post_topics',
        'can_edit_topics',
        'can_add_comments',
        'can_delete_comments',
        'can_view_board',
        'can_view_offline_board',
        'flood_control',
        'can_override_locked_topics',
        'icon',
        'can_shout',
        'can_moderate',
        'can_delete_shouts',
        'can_delete_own_shouts',
        'can_karma',
        'can_im',
        'can_pm',
        'can_lock_own_topics',
        'can_delete_own_topics',
        'can_use_sigs',
        'can_attach',
        'can_delete_own_posts',
        'can_poll',
        'can_access_acp',
        'can_view_shoutbox',
        'can_view_stats',
        'legend',
        'can_view_fullprofile',
    ];

    public int $id = 0;

    public string $title = '';

    public int $can_post = 0;

    public int $can_edit_posts = 0;

    public int $can_post_topics = 0;

    public int $can_edit_topics = 0;

    public int $can_add_comments = 0;

    public int $can_delete_comments = 0;

    public int $can_view_board = 0;

    public int $can_view_offline_board = 0;

    public int $flood_control = 0;

    public int $can_override_locked_topics = 0;

    public string $icon = '';

    public int $can_shout = 0;

    public int $can_moderate = 0;

    public int $can_delete_shouts = 0;

    public int $can_delete_own_shouts = 0;

    public int $can_karma = 0;

    public int $can_im = 0;

    public int $can_pm = 0;

    public int $can_lock_own_topics = 0;

    public int $can_delete_own_topics = 0;

    public int $can_use_sigs = 0;

    public int $can_attach = 0;

    public int $can_delete_own_posts = 0;

    public int $can_poll = 0;

    public int $can_access_acp = 0;

    public int $can_view_shoutbox = 0;

    public int $can_view_stats = 0;

    public int $legend = 0;

    public int $can_view_fullprofile = 1;
}
