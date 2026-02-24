<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Model;

final class Member extends Model
{
    public const string TABLE = 'members';

    #[Column(name: 'id', type: 'int', nullable: false, autoIncrement: true, unsigned: true)]
    #[PrimaryKey]
    public int $id = 0;

    #[Column(name: 'groupID', type: 'int', unsigned: true)]
    #[ForeignKey(table: 'member_groups', field: 'id', onDelete: 'null')]
    public int $groupID = 0;

    #[Column(name: 'name', type: 'string', length: 50, nullable: false)]
    public string $name = '';

    #[Column(name: 'pass', type: 'string', default: '', length: 255, nullable: false)]
    public string $pass = '';

    /**
     * Auto-generated
     */
    #[Column(name: 'ip', type: 'binary', default: '', length: 16, nullable: false)]
    public string $ip = '';

    #[Column(name: 'lastVisit', type: 'datetime')]
    public string $lastVisit = '';

    #[Column(name: 'posts', type: 'int', default: 0, nullable: false, unsigned: true)]
    public int $posts = 0;

    /**
     * Profile information
     */
    #[Column(name: 'about', type: 'text', default: '', nullable: false)]
    public string $about = '';

    #[Column(name: 'avatar', type: 'string', default: '', length: 255, nullable: false)]
    public string $avatar = '';

    #[Column(name: 'birthdate', type: 'date')]
    public ?string $birthdate = null;

    #[Column(name: 'displayName', type: 'string', default: '', length: 30, nullable: false)]
    #[Key]
    public string $displayName = '';

    #[Column(name: 'email', type: 'string', default: '', length: 50, nullable: false)]
    public string $email = '';

    #[Column(name: 'enemies', type: 'text', default: '', nullable: false)]
    public string $enemies = '';

    #[Column(name: 'fullName', type: 'string', default: '', length: 50, nullable: false)]
    public string $fullName = '';

    #[Column(name: 'friends', type: 'text', default: '', nullable: false)]
    public string $friends = '';

    #[Column(name: 'gender', type: 'string', default: '', length: 50, nullable: false)]
    public string $gender = '';

    #[Column(name: 'joinDate', type: 'datetime')]
    public ?string $joinDate = null;

    #[Column(name: 'location', type: 'string', default: '', length: 100, nullable: false)]
    public string $location = '';

    #[Column(name: 'sig', type: 'text', default: '', nullable: false)]
    public string $sig = '';

    #[Column(name: 'ucpnotepad', type: 'string', default: '', length: 2000, nullable: false)]
    public string $ucpnotepad = '';

    #[Column(name: 'usertitle', type: 'string', default: '', length: 255, nullable: false)]
    public string $usertitle = '';

    #[Column(name: 'website', type: 'string', default: '', length: 255, nullable: false)]
    public string $website = '';

    /**
     * Board customization
     */
    #[Column(name: 'emailSettings', type: 'tinyint', default: 0, nullable: false, unsigned: true)]
    public int $emailSettings = 0;

    #[Column(name: 'itemsPerPage', type: 'int', unsigned: true)]
    public ?int $itemsPerPage = 10;

    #[Column(name: 'mod', type: 'bool')]
    public int $mod = 0;

    #[Column(name: 'nowordfilter', type: 'bool')]
    public int $nowordfilter = 0;

    #[Column(name: 'skinID', type: 'int', unsigned: true)]
    public ?int $skinID = null;

    #[Column(name: 'wysiwyg', type: 'bool')]
    public int $wysiwyg = 1;

    /**
     * Sound/notification settings
     */
    #[Column(name: 'notifyPM', type: 'bool')]
    public int $notifyPM = 0;

    #[Column(name: 'notifyPostInMyTopic', type: 'bool')]
    public int $notifyPostInMyTopic = 0;

    #[Column(name: 'notifyPostInSubscribedTopic', type: 'bool')]
    public int $notifyPostInSubscribedTopic = 0;

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

    /**
     * Contact Information
     */
    #[Column(name: 'contactAIM', type: 'string', default: '', length: 50, nullable: false)]
    public string $contactAIM = '';

    #[Column(name: 'contactBlueSky', type: 'string', default: '', length: 50, nullable: false)]
    public string $contactBlueSky = '';

    #[Column(name: 'contactDiscord', type: 'string', default: '', length: 50, nullable: false)]
    public string $contactDiscord = '';

    #[Column(name: 'contactGoogleChat', type: 'string', default: '', length: 50, nullable: false)]
    public string $contactGoogleChat = '';

    #[Column(name: 'contactMSN', type: 'string', default: '', length: 50, nullable: false)]
    public string $contactMSN = '';

    #[Column(name: 'contactSkype', type: 'string', default: '', length: 50, nullable: false)]
    public string $contactSkype = '';

    #[Column(name: 'contactSteam', type: 'string', default: '', length: 50, nullable: false)]
    public string $contactSteam = '';

    #[Column(name: 'contactTwitter', type: 'string', default: '', length: 50, nullable: false)]
    public string $contactTwitter = '';

    #[Column(name: 'contactYIM', type: 'string', default: '', length: 50, nullable: false)]
    public string $contactYIM = '';

    #[Column(name: 'contactYoutube', type: 'string', default: '', length: 50, nullable: false)]
    public string $contactYoutube = '';
}
