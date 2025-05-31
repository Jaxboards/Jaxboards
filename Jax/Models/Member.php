<?php

declare(strict_types=1);

namespace Jax\Models;

use Jax\Model;

final class Member extends Model
{
    public const TABLE = 'members';

    public const FIELDS = [
        'id',
        'name',
        'pass',
        'email',
        'sig',
        'posts',
        'groupID',
        'avatar',
        'usertitle',
        'joinDate',
        'lastVisit',
        'contactSkype',
        'contactYIM',
        'contactMSN',
        'contactGoogleChat',
        'contactAIM',
        'website',
        'birthdate',
        'about',
        'displayName',
        'full_name',
        'contactSteam',
        'location',
        'gender',
        'friends',
        'enemies',
        'soundShout',
        'soundIM',
        'soundPM',
        'soundPostInMyTopic',
        'soundPostInSubscribedTopic',
        'notifyPM',
        'notifyPostInMyTopic',
        'notifyPostInSubscribedTopic',
        'ucpnotepad',
        'skinID',
        'contactTwitter',
        'contactDiscord',
        'contactYoutube',
        'contactBlueSky',
        'emailSettings',
        'nowordfilter',
        'ip',
        'mod',
        'wysiwyg',
    ];

    public int $id = 0;

    public string $name = '';

    public string $pass = '';

    public string $email = '';

    public string $sig = '';

    public int $posts = 0;

    public int $groupID = 0;

    public string $avatar = '';

    public string $usertitle = '';

    public ?string $joinDate = null;

    public string $lastVisit = '';

    public string $contactSkype = '';

    public string $contactYIM = '';

    public string $contactMSN = '';

    public string $contactGoogleChat = '';

    public string $contactAIM = '';

    public string $website = '';

    public ?string $birthdate = null;

    public string $about = '';

    public string $displayName = '';

    public string $full_name = '';

    public string $contactSteam = '';

    public string $location = '';

    public string $gender = '';

    public string $friends = '';

    public string $enemies = '';

    public int $soundShout = 0;

    public int $soundIM = 0;

    public int $soundPM = 0;

    public int $soundPostInMyTopic = 0;

    public int $soundPostInSubscribedTopic = 0;

    public int $notifyPM = 0;

    public int $notifyPostInMyTopic = 0;

    public int $notifyPostInSubscribedTopic = 0;

    public string $ucpnotepad = '';

    public ?int $skinID = null;

    public string $contactTwitter = '';

    public string $contactDiscord = '';

    public string $contactYoutube = '';

    public string $contactBlueSky = '';

    public int $emailSettings = 0;

    public int $nowordfilter = 0;

    public string $ip = '';

    public int $mod = 0;

    public int $wysiwyg = 1;
}
