<?php

declare(strict_types=1);

namespace Jax;

use Jax\Constants\Groups;
use Jax\Models\Forum;
use Jax\Models\Group;
use Jax\Models\Member;
use Jax\Models\Topic;

use function explode;
use function in_array;
use function password_hash;
use function password_needs_rehash;
use function password_verify;

use const PASSWORD_DEFAULT;

final class User
{
    private Member $member;

    public function __construct(
        private readonly Jax $jax,
        private readonly IPAddress $ipAddress,
        // Exposing these for testing
        ?Member $member = null,
        public ?Group $userPerms = null,
    ) {
        if ($member !== null) {
            $this->member = $member;

            return;
        }

        $guestMember = new Member();
        $guestMember->groupID = Groups::Guest->value;

        $this->member = $guestMember;
    }

    public function get(): Member
    {
        return $this->member;
    }

    public function set(string $property, int|string|null $value): void
    {
        $this->setBulk([$property => $value]);
    }

    /**
     * @param array<string,null|float|int|string> $fields
     */
    public function setBulk(array $fields): void
    {
        if ($this->isGuest()) {
            return;
        }

        foreach ($fields as $key => $value) {
            $this->member->{$key} = $value;
        }

        $this->member->update();
    }

    public function login(?int $uid = null, ?string $pass = null): Member
    {
        if ($this->member->id !== 0 || !$uid) {
            return $this->member;
        }

        $user = Member::selectOne($uid);

        if ($user === null) {
            return $this->member;
        }

        if ($pass && !$this->verifyPassword($user, $pass)) {
            return $this->member;
        }

        return $this->member = $user;
    }

    public function getGroup(): ?Group
    {
        if ($this->userPerms !== null) {
            return $this->userPerms;
        }

        $groupId = match (true) {
            $this->isBanned() => Groups::Banned->value,
            default => $this->member->groupID,
        };

        $group = Group::selectOne($groupId);
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

        $permFlags = $parsedPerms[$this->member->groupID] ?? null;

        // Null $permFlags means to fall back to global permissions.
        if ($permFlags !== null) {
            return $permFlags;
        }

        return [
            'poll' => (bool) $this->getGroup()?->canPoll,
            'read' => true,
            // There is no global "forum read" permission so default to assuming the user can read it
            'reply' => (bool) $this->getGroup()?->canPost,
            'start' => (bool) $this->getGroup()?->canCreateTopics,
            'upload' => (bool) $this->getGroup()?->canAttach,
            'view' => true,
            // There is no global "forum view" permission so default to assuming the user can see it
        ];
    }

    public function isAdmin(): bool
    {
        return $this->member->groupID === Groups::Admin->value;
    }

    public function isBanned(): bool
    {
        if ($this->member->groupID === Groups::Banned->value) {
            return true;
        }

        return $this->ipAddress->isBanned();
    }

    public function isGuest(): bool
    {
        return $this->member->groupID === Groups::Guest->value;
    }

    public function isModerator(): bool
    {
        return (bool) $this->getGroup()?->canModerate;
    }

    public function isModeratorOfTopic(Topic $modelsTopic): bool
    {
        if ($this->isModerator()) {
            return true;
        }

        if ($this->member->mod !== 0) {
            $forum = Forum::selectOne($modelsTopic->fid);

            if (
                $forum !== null
                && in_array((string) $this->member->id, explode(',', $forum->mods), true)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool if password is correct
     */
    private function verifyPassword(Member $member, string $pass): bool
    {
        if (!password_verify($pass, $member->pass)) {
            return false;
        }

        if (password_needs_rehash($member->pass, PASSWORD_DEFAULT)) {
            // Add the new hash.
            $member->pass = password_hash($pass, PASSWORD_DEFAULT);
            $member->update();
        }

        return true;
    }
}
