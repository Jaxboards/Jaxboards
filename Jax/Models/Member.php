<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use Jax\Model;

final class Member extends Model
{
    public const TABLE = 'members';

    #[PrimaryKey]
    #[Column(name: 'id', type: 'int', unsigned: true, nullable: false, autoIncrement: true)]
    public int $id = 0;

    #[Column(name: 'name', type: 'string', length: 50, nullable: false)]
    public string $name = '';

    #[Column(name: 'pass', type: 'string', length: 255, nullable: false, default: '')]
    public string $pass = '';

    #[Column(name: 'email', type: 'string', length: 50, nullable: false, default: '')]
    public string $email = '';

    #[Column(name: 'sig', type: 'text', nullable: false, default: '')]
    public string $sig = '';

    #[Column(name: 'posts', type: 'int', unsigned: true, nullable: false, default: 0)]
    public int $posts = 0;

    #[Column(name: 'groupID', type: 'int', unsigned: true)]
    public int $groupID = 0;

    #[Column(name: 'avatar', type: 'string', length: 255, nullable: false, default: '')]
    public string $avatar = '';

    #[Column(name: 'usertitle', type: 'string', length: 255, nullable: false, default: '')]
    public string $usertitle = '';

    #[Column(name: 'joinDate', type: 'datetime')]
    public ?string $joinDate = null;

    #[Column(name: 'lastVisit', type: 'datetime')]
    public string $lastVisit = '';

    #[Column(name: 'contactSkype', type: 'string', length: 50, nullable: false, default: '')]
    public string $contactSkype = '';

    #[Column(name: 'contactYIM', type: 'string', length: 50, nullable: false, default: '')]
    public string $contactYIM = '';

    #[Column(name: 'contactMSN', type: 'string', length: 50, nullable: false, default: '')]
    public string $contactMSN = '';

    #[Column(name: 'contactGoogleChat', type: 'string', length: 50, nullable: false, default: '')]
    public string $contactGoogleChat = '';

    #[Column(name: 'contactAIM', type: 'string', length: 50, nullable: false, default: '')]
    public string $contactAIM = '';

    #[Column(name: 'website', type: 'string', length: 255, nullable: false, default: '')]
    public string $website = '';

    #[Column(name: 'birthdate', type: 'date')]
    public ?string $birthdate = null;

    #[Column(name: 'about', type: 'text', nullable: false, default: '')]
    public string $about = '';

    #[Column(name: 'displayName', type: 'string', length: 30, nullable: false, default: '')]
    public string $displayName = '';

    #[Column(name: 'full_name', type: 'string', length: 50, nullable: false, default: '')]
    public string $full_name = '';

    #[Column(name: 'contactSteam', type: 'string', length: 50, nullable: false, default: '')]
    public string $contactSteam = '';

    #[Column(name: 'location', type: 'string', length: 100, nullable: false, default: '')]
    public string $location = '';

    #[Column(name: 'location', type: 'string', length: 10, nullable: false, default: '')]
    public string $gender = '';

    #[Column(name: 'friends', type: 'text', nullable: false, default: '')]
    public string $friends = '';

    #[Column(name: 'enemies', type: 'text', nullable: false, default: '')]
    public string $enemies = '';

    #[Column(name: 'soundShout', type: 'bool', default: true)]
    public int $soundShout = 1;

    #[Column(name: 'soundIM', type: 'bool', default: true)]
    public int $soundIM = 1;

    #[Column(name: 'soundPM', type: 'bool')]
    public int $soundPM = 0;

    #[Column(name: 'soundPostInMyTopic', type: 'bool')]
    public int $soundPostInMyTopic = 0;

    #[Column(name: 'soundPostInSubscribedTopic', type: 'bool')]
    public int $soundPostInSubscribedTopic = 0;

    #[Column(name: 'notifyPM', type: 'bool')]
    public int $notifyPM = 0;

    #[Column(name: 'notifyPostInMyTopic', type: 'bool')]
    public int $notifyPostInMyTopic = 0;

    #[Column(name: 'notifyPostInSubscribedTopic', type: 'bool')]
    public int $notifyPostInSubscribedTopic = 0;

    #[Column(name: 'ucpnotepad', type: 'string', length: 2000, nullable: false, default: '')]
    public string $ucpnotepad = '';

    #[Column(name: 'skinID', type: 'int', unsigned: true)]
    public ?int $skinID = null;

    #[Column(name: 'contactTwitter', type: 'string', length: 50, nullable: false, default: '')]
    public string $contactTwitter = '';

    #[Column(name: 'contactDiscord', type: 'string', length: 50, nullable: false, default: '')]
    public string $contactDiscord = '';

    #[Column(name: 'contactYoutube', type: 'string', length: 50, nullable: false, default: '')]
    public string $contactYoutube = '';

    #[Column(name: 'contactBlueSky', type: 'string', length: 50, nullable: false, default: '')]
    public string $contactBlueSky = '';

    #[Column(name: 'emailSettings', type: 'tinyint', unsigned: true, nullable: false, default: 0)]
    public int $emailSettings = 0;

    #[Column(name: 'nowordfilter', type: 'bool')]
    public int $nowordfilter = 0;

    #[Column(name: 'ip', type: 'binary', length: 16, nullable: false, default: '')]
    public string $ip = '';

    #[Column(name: 'mod', type: 'bool')]
    public int $mod = 0;

    #[Column(name: 'wysiwyg', type: 'bool')]
    public int $wysiwyg = 1;
}
