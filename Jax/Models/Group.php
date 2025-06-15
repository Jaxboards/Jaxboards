<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Group extends Model
{
    public const TABLE = 'member_groups';

    #[PrimaryKey]
    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false, autoIncrement: true)]
    public int $id = 0;

    #[Column(name: 'title', type: 'string', length: 255, nullable: false)]
    public string $title = '';

    #[Column(name: 'canPost', type: 'bool')]
    public int $canPost = 0;

    #[Column(name: 'canEditPosts', type: 'bool')]
    public int $canEditPosts = 0;

    #[Column(name: 'canCreateTopics', type: 'bool')]
    public int $canCreateTopics = 0;

    #[Column(name: 'canEditTopics', type: 'bool')]
    public int $canEditTopics = 0;

    #[Column(name: 'canAddComments', type: 'bool')]
    public int $canAddComments = 0;

    #[Column(name: 'canDeleteComments', type: 'bool')]
    public int $canDeleteComments = 0;

    #[Column(name: 'canViewBoard', type: 'bool')]
    public int $canViewBoard = 0;

    #[Column(name: 'canViewOfflineBoard', type: 'bool')]
    public int $canViewOfflineBoard = 0;

    #[Column(name: 'floodControl', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $floodControl = 0;

    #[Column(name: 'canOverrideLockedTopics', type: 'bool')]
    public int $canOverrideLockedTopics = 0;

    #[Column(name: 'icon', type: 'string', length: 255, nullable: false, default: '')]
    public string $icon = '';

    #[Column(name: 'canShout', type: 'bool')]
    public int $canShout = 0;

    #[Column(name: 'canModerate', type: 'bool')]
    public int $canModerate = 0;

    #[Column(name: 'canDeleteShouts', type: 'bool')]
    public int $canDeleteShouts = 0;

    #[Column(name: 'canDeleteOwnShouts', type: 'bool')]
    public int $canDeleteOwnShouts = 0;

    #[Column(name: 'canKarma', type: 'bool')]
    public int $canKarma = 0;

    #[Column(name: 'canIM', type: 'bool')]
    public int $canIM = 0;

    #[Column(name: 'canPM', type: 'bool')]
    public int $canPM = 0;

    #[Column(name: 'canLockOwnTopics', type: 'bool')]
    public int $canLockOwnTopics = 0;

    #[Column(name: 'canDeleteOwnTopics', type: 'bool')]
    public int $canDeleteOwnTopics = 0;

    #[Column(name: 'canUseSignatures', type: 'bool')]
    public int $canUseSignatures = 0;

    #[Column(name: 'canAttach', type: 'bool')]
    public int $canAttach = 0;

    #[Column(name: 'canDeleteOwnPosts', type: 'bool')]
    public int $canDeleteOwnPosts = 0;

    #[Column(name: 'canPoll', type: 'bool')]
    public int $canPoll = 0;

    #[Column(name: 'canAccessACP', type: 'bool')]
    public int $canAccessACP = 0;

    #[Column(name: 'canViewShoutbox', type: 'bool')]
    public int $canViewShoutbox = 0;

    #[Column(name: 'canViewStats', type: 'bool')]
    public int $canViewStats = 0;

    #[Column(name: 'legend', type: 'bool')]
    public int $legend = 0;

    #[Column(name: 'canViewFullProfile', type: 'bool')]
    public int $canViewFullProfile = 1;
}
