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
        'canPost',
        'canEditPosts',
        'canCreateTopics',
        'canEditTopics',
        'canAddComments',
        'canDeleteComments',
        'canViewBoard',
        'canViewOfflineBoard',
        'floodControl',
        'canOverrideLockedTopics',
        'icon',
        'canShout',
        'canModerate',
        'canDeleteShouts',
        'canDeleteOwnShouts',
        'canKarma',
        'canIM',
        'canPM',
        'canLockOwnTopics',
        'canDeleteOwnTopics',
        'canUseSignatures',
        'canAttach',
        'canDeleteOwnPosts',
        'canPoll',
        'canAccessACP',
        'canViewShoutbox',
        'canViewStats',
        'legend',
        'canViewFullProfile',
    ];

    public int $id = 0;

    public string $title = '';

    public int $canPost = 0;

    public int $canEditPosts = 0;

    public int $canCreateTopics = 0;

    public int $canEditTopics = 0;

    public int $canAddComments = 0;

    public int $canDeleteComments = 0;

    public int $canViewBoard = 0;

    public int $canViewOfflineBoard = 0;

    public int $floodControl = 0;

    public int $canOverrideLockedTopics = 0;

    public string $icon = '';

    public int $canShout = 0;

    public int $canModerate = 0;

    public int $canDeleteShouts = 0;

    public int $canDeleteOwnShouts = 0;

    public int $canKarma = 0;

    public int $canIM = 0;

    public int $canPM = 0;

    public int $canLockOwnTopics = 0;

    public int $canDeleteOwnTopics = 0;

    public int $canUseSignatures = 0;

    public int $canAttach = 0;

    public int $canDeleteOwnPosts = 0;

    public int $canPoll = 0;

    public int $canAccessACP = 0;

    public int $canViewShoutbox = 0;

    public int $canViewStats = 0;

    public int $legend = 0;

    public int $canViewFullProfile = 1;
}
