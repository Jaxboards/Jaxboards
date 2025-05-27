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
        'group_id',
        'avatar',
        'usertitle',
        'join_date',
        'last_visit',
        'contact_skype',
        'contact_yim',
        'contact_msn',
        'contact_gtalk',
        'contact_aim',
        'website',
        'birthdate',
        'about',
        'display_name',
        'full_name',
        'contact_steam',
        'location',
        'gender',
        'friends',
        'enemies',
        'sound_shout',
        'sound_im',
        'sound_pm',
        'sound_postinmytopic',
        'sound_postinsubscribedtopic',
        'notify_pm',
        'notify_postinmytopic',
        'notify_postinsubscribedtopic',
        'ucpnotepad',
        'skin_id',
        'contact_twitter',
        'contact_discord',
        'contact_youtube',
        'contact_bluesky',
        'email_settings',
        'nowordfilter',
        'ip',
        'mod',
        'wysiwyg',
    ];

    public int $id;

    public string $name;

    public string $pass;

    public string $email;

    public string $sig;

    public int $posts;

    public int $group_id;

    public string $avatar;

    public string $usertitle;

    public string $join_date;

    public string $last_visit;

    public string $contact_skype;

    public string $contact_yim;

    public string $contact_msn;

    public string $contact_gtalk;

    public string $contact_aim;

    public string $website;

    public ?string $birthdate = null;

    public string $about;

    public string $display_name;

    public string $full_name;

    public string $contact_steam;

    public string $location;

    public string $gender;

    public string $friends;

    public string $enemies;

    public int $sound_shout;

    public int $sound_im;

    public int $sound_pm;

    public int $sound_postinmytopic;

    public int $sound_postinsubscribedtopic;

    public int $notify_pm;

    public int $notify_postinmytopic;

    public int $notify_postinsubscribedtopic;

    public string $ucpnotepad;

    public ?int $skin_id = null;

    public string $contact_twitter;

    public string $contact_discord;

    public string $contact_youtube;

    public string $contact_bluesky;

    public int $email_settings;

    public int $nowordfilter;

    public string $ip;

    public int $mod;

    public int $wysiwyg;
}
