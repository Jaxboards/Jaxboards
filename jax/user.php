<?php

declare(strict_types=1);

namespace Jax;

use function array_merge;
use function count;
use function floor;
use function glob;
use function gmdate;
use function is_array;
use function is_dir;
use function json_decode;
use function mail;
use function mb_substr;
use function preg_match;
use function rmdir;
use function round;
use function setcookie;
use function str_replace;
use function strtotime;
use function time;
use function unlink;
use function unpack;

use const PHP_EOL;

final class User
{
    public function __construct(private readonly Database $database) {}

    public function getUser($uid = false, $pass = false)
    {
        static $userData = false;

        if ($userData) {
            return $userData;
        }

        if (!$uid) {
            return false;
        }

        $result = $this->database->safeselect(
            [
                'about',
                'avatar',
                'birthdate',
                'contact_aim',
                'contact_bluesky',
                'contact_discord',
                'contact_gtalk',
                'contact_msn',
                'contact_skype',
                'contact_steam',
                'contact_twitter',
                'contact_yim',
                'contact_youtube',
                'display_name',
                'email_settings',
                'email',
                'enemies',
                'friends',
                'full_name',
                'gender',
                'group_id',
                'id',
                'ip',
                'location',
                '`mod`',
                'name',
                'notify_pm',
                'notify_postinmytopic',
                'notify_postinsubscribedtopic',
                'nowordfilter',
                'pass',
                'posts',
                'sig',
                'skin_id',
                'sound_im',
                'sound_pm',
                'sound_postinmytopic',
                'sound_postinsubscribedtopic',
                'sound_shout',
                'ucpnotepad',
                'usertitle',
                'website',
                'wysiwyg',
                "CONCAT(MONTH(`birthdate`),' ',DAY(`birthdate`)) as `birthday`",
                'DAY(`birthdate`) AS `dob_day`',
                'MONTH(`birthdate`) AS `dob_month`',
                'UNIX_TIMESTAMP(`join_date`) AS `join_date`',
                'UNIX_TIMESTAMP(`last_visit`) AS `last_visit`',
                'YEAR(`birthdate`) AS `dob_year`',
            ],
            'members',
            'WHERE `id`=?',
            $this->database->basicvalue($uid),
        );
        $user = $this->database->arow($result);
        $this->database->disposeresult($result);

        if (empty($user)) {
            return $userData = false;
        }

        $user['birthday'] = (date('n j') === $user['birthday'] ? 1 : 0);

        // Password parsing.
        if ($pass !== false) {
            $verifiedPassword = password_verify((string) $pass, (string) $user['pass']);

            if (!$verifiedPassword) {
                return $userData = false;
            }

            $needsRehash = password_needs_rehash(
                $user['pass'],
                PASSWORD_DEFAULT,
            );

            if ($needsRehash) {
                $new_hash = password_hash((string) $pass, PASSWORD_DEFAULT);
                // Add the new hash.
                $this->safeupdate(
                    'members',
                    [
                        'pass' => $new_hash,
                    ],
                    'WHERE `id` = ?',
                    $user['id'],
                );
            }

            unset($user['pass']);
        }

        $userData = $user;

        return $userData;
    }

    public function getPerms($groupId = null)
    {
        static $userPerms = null;

        if ($groupId === null && $userPerms !== null) {
            return $userPerms;
        }

        $userData = $this->getUser();

        if ($groupId === null && $userData) {
            $groupId = $userData['group_id'];
        }


        $result = $this->database->safeselect(
            <<<'EOT'
                `can_access_acp`,
                `can_add_comments`,
                `can_attach`,
                `can_delete_comments`,
                `can_delete_own_posts`,
                `can_delete_own_shouts`,
                `can_delete_own_topics`,
                `can_delete_shouts`,
                `can_edit_posts`,
                `can_edit_topics`,
                `can_im`,
                `can_karma`,
                `can_lock_own_topics`,
                `can_moderate`,
                `can_override_locked_topics`,
                `can_pm`,
                `can_poll`,
                `can_post_topics`,
                `can_post`,
                `can_shout`,
                `can_use_sigs`,
                `can_view_board`,
                `can_view_fullprofile`,
                `can_view_offline_board`,
                `can_view_shoutbox`,
                `can_view_stats`,
                `flood_control`,
                `icon`,
                `id`,
                `legend`,
                `title`
                EOT
            ,
            'member_groups',
            'WHERE `id`=?',
            $groupId ?? 3,
        );
        $retval = $this->database->arow($result);
        $userPerms = $retval;
        $this->database->disposeresult($result);

        return $userPerms;
    }
}
