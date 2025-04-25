<?php

declare(strict_types=1);

namespace Jax;

use function array_merge;
use function count;
use function date;
use function is_string;
use function password_hash;
use function password_needs_rehash;
use function password_verify;
use function unpack;

use const PASSWORD_DEFAULT;

final class User
{
    public ?array $userData = null;

    public ?array $userPerms = null;

    public function __construct(
        private readonly Database $database,
        private readonly IPAddress $ipAddress,
    ) {}

    public function get(string $property): null|int|string
    {
        if (!$this->userData) {
            // TODO: default other property values for guests
            return match ($property) {
                'group_id' => 3,
                default => null,
            };
        }

        return $this->userData[$property] ?? null;
    }

    public function set(string $property, $value): void
    {
        $this->setBulk([$property => $value]);
    }

    public function setBulk(array $fields): void
    {
        $this->userData = array_merge($this->userData, $fields);
        $this->database->safeupdate(
            'members',
            $fields,
            ' WHERE `id`=?',
            $this->get('id'),
        );
    }

    public function getUser($uid = false, $pass = false): ?array
    {
        if ($this->userData) {
            return $this->userData;
        }

        if (!$uid) {
            return null;
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

        if (!$user) {
            return $this->userData = null;
        }

        $user['birthday'] = (date('n j') === $user['birthday'] ? 1 : 0);

        // Password parsing.
        if ($pass !== false) {
            $verifiedPassword = password_verify((string) $pass, (string) $user['pass']);

            if (!$verifiedPassword) {
                return $this->userData = null;
            }

            $needsRehash = password_needs_rehash(
                $user['pass'],
                PASSWORD_DEFAULT,
            );

            if ($needsRehash) {
                $newHash = password_hash((string) $pass, PASSWORD_DEFAULT);
                // Add the new hash.
                $this->database->safeupdate(
                    'members',
                    [
                        'pass' => $newHash,
                    ],
                    'WHERE `id` = ?',
                    $user['id'],
                );
            }

            unset($user['pass']);
        }

        return $this->userData = $user;
    }

    public function getPerm(string $perm): null|int|string
    {
        $perms = $this->getPerms();

        return $perms[$perm] ?? null;
    }

    public function getPerms($groupId = null): ?array
    {

        if ($groupId === null) {
            if ($this->userPerms !== null) {
                return $this->userPerms;
            }

            if ($this->userData) {
                $groupId = $this->userData['group_id'];
            }

            if ($this->isBanned()) {
                $groupId = 4;
            }
        }

        $result = $this->database->safeselect(
            [
                'can_access_acp',
                'can_add_comments',
                'can_attach',
                'can_delete_comments',
                'can_delete_own_posts',
                'can_delete_own_shouts',
                'can_delete_own_topics',
                'can_delete_shouts',
                'can_edit_posts',
                'can_edit_topics',
                'can_im',
                'can_karma',
                'can_lock_own_topics',
                'can_moderate',
                'can_override_locked_topics',
                'can_pm',
                'can_poll',
                'can_post_topics',
                'can_post',
                'can_shout',
                'can_use_sigs',
                'can_view_board',
                'can_view_fullprofile',
                'can_view_offline_board',
                'can_view_shoutbox',
                'can_view_stats',
                'flood_control',
                'icon',
                'id',
                'legend',
                'title',
            ],
            'member_groups',
            'WHERE `id`=?',
            $groupId ?? 3,
        );
        $retval = $this->database->arow($result);
        $this->userPerms = $retval;
        $this->database->disposeresult($result);

        return $this->userPerms;
    }

    /**
     * This function expands the permissions bitflag into a readable associative array.
     *
     * This function can receive either a binary encoded string (which is an array of all group bitflags)
     * Or an integer (bitflag) for a specific group's permissions.
     */
    public function parseForumPerms(int|string $permstoparse): array
    {
        // If it's a binary string, unpack it into all group bitflags and choose
        // the bitflag as determined by the user's group.
        if (is_string($permstoparse)) {
            $unpack = unpack('n*', $permstoparse);
            $parsedPerms = [];
            $counter = count($unpack);
            for ($x = 1; $x < $counter; $x += 2) {
                $parsedPerms[$unpack[$x]] = $unpack[$x + 1];
            }

            $permFlags = $parsedPerms[$this->get('group_id')] ?? null;
        } else {
            $permFlags = $permstoparse;
        }

        // Null $permFlags means to fall back to global permissions.
        if ($permFlags !== null) {
            $bitFlagOrder = ['upload', 'reply', 'start', 'read', 'view', 'poll'];

            $decoded = [];
            foreach ($bitFlagOrder as $index => $name) {
                $decoded[$name] = ($permFlags & 1 << $index) !== 0 ? 1 : 0;
            }

            return $decoded;
        }

        return [
            'poll' => $this->getPerm('can_poll'),
            'read' => 1,
            // There is no global "forum read" permission so default to assuming the user can read it
            'reply' => $this->getPerm('can_post'),
            'start' => $this->getPerm('can_post_topics'),
            'upload' => $this->getPerm('can_attach'),
            'view' => 1,
            // There is no global "forum view" permission so default to assuming the user can see it
        ];
    }

    public function isAdmin(): bool
    {
        return $this->get('group_id') === 2;
    }

    public function isBanned(): bool
    {
        if ($this->get('group_id') === 4) {
            return true;
        }

        return $this->ipAddress->isBanned();
    }

    public function isGuest(): bool
    {
        return !$this->getUser();
    }
}
