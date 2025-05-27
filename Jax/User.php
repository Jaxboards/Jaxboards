<?php

declare(strict_types=1);

namespace Jax;

use Carbon\Carbon;
use Jax\Constants\Groups;
use Jax\Models\Group;

use function array_merge;
use function password_hash;
use function password_needs_rehash;
use function password_verify;

use const PASSWORD_DEFAULT;

final class User
{
    /**
     * @param null|array<array-key,mixed> $userData
     */
    public function __construct(
        private readonly Database $database,
        private readonly Jax $jax,
        private readonly IPAddress $ipAddress,
        // Exposing these for testing
        private ?array $userData = null,
        public ?Group $userPerms = null,
    ) {}

    public function get(string $property): null|int|string
    {
        if (!$this->userData) {
            return match ($property) {
                'group_id' => Groups::Guest->value,
                default => null,
            };
        }

        return $this->userData[$property] ?? null;
    }

    public function set(string $property, null|int|string $value): void
    {
        $this->setBulk([$property => $value]);
    }

    /**
     * @param array<string,null|float|int|string> $fields
     */
    public function setBulk(array $fields): void
    {
        $this->userData = array_merge($this->userData ?? [], $fields);
        $this->database->update(
            'members',
            $fields,
            ' WHERE `id`=?',
            $this->get('id'),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function getUser(?int $uid = null, ?string $pass = null): ?array
    {
        if ($this->userData || !$uid) {
            return $this->userData;
        }

        $result = $this->database->select(
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
            Database::WHERE_ID_EQUALS,
            $uid,
        );
        $user = $this->database->arow($result);
        $this->database->disposeresult($result);

        if ($user) {
            $user['birthday'] = Carbon::now()->format('n j') === $user['birthday'];

            // Password parsing.
            if ($pass && !$this->verifyPassword($user, $pass)) {
                $user = null;
            }
        }

        return $this->userData = $user;
    }

    public function getPerm(string $perm): null|bool|int|string
    {
        $perms = $this->getPerms();

        return $perms->{$perm} ?? null;
    }

    public function getPerms(): ?Group
    {
        if ($this->userPerms !== null) {
            return $this->userPerms;
        }

        $groupId = match (true) {
            $this->isBanned() => Groups::Banned->value,
            $this->userData !== null => $this->get('group_id'),
            default => null,
        };

        $group = Group::selectOne(
            $this->database,
            Database::WHERE_ID_EQUALS,
            $groupId ?? Groups::Guest->value,
        );
        $this->userPerms = $group;

        return $this->userPerms;
    }

    /**
     * Given a forum permission's binary-encoded string,
     * returns the user's (merged) permissions for the forum.
     *
     * @return array<string,bool>
     */
    public function getForumPerms(string $forumPerms): array
    {
        // If it's a binary string, unpack it into all group bitflags and choose
        // the bitflag as determined by the user's group.
        $parsedPerms = $this->jax->parseForumPerms($forumPerms);

        $permFlags = $parsedPerms[$this->get('group_id')] ?? null;

        // Null $permFlags means to fall back to global permissions.
        if ($permFlags !== null) {
            return $permFlags;
        }

        return [
            'poll' => (bool) $this->getPerm('can_poll'),
            'read' => true,
            // There is no global "forum read" permission so default to assuming the user can read it
            'reply' => (bool) $this->getPerm('can_post'),
            'start' => (bool) $this->getPerm('can_post_topics'),
            'upload' => (bool) $this->getPerm('can_attach'),
            'view' => true,
            // There is no global "forum view" permission so default to assuming the user can see it
        ];
    }

    public function isAdmin(): bool
    {
        return $this->get('group_id') === Groups::Admin->value;
    }

    public function isBanned(): bool
    {
        if ($this->get('group_id') === Groups::Banned->value) {
            return true;
        }

        return $this->ipAddress->isBanned();
    }

    public function isGuest(): bool
    {
        return !$this->getUser();
    }

    /**
     * @param array<string,mixed> $user
     *
     * @return bool if password is correct
     */
    private function verifyPassword(array $user, string $pass): bool
    {
        if (!password_verify($pass, (string) $user['pass'])) {
            return false;
        }

        if (password_needs_rehash($user['pass'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($pass, PASSWORD_DEFAULT);
            // Add the new hash.
            $this->database->update(
                'members',
                [
                    'pass' => $newHash,
                ],
                'WHERE `id` = ?',
                $user['id'],
            );
        }

        return true;
    }
}
