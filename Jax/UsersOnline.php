<?php

declare(strict_types=1);

namespace Jax;

use Carbon\Carbon;
use Jax\Models\Member;
use Jax\Models\Session;

use function gmdate;

final class UsersOnline
{
    private int $guestCount = 0;

    /**
     * @var array<string,mixed>
     */
    private array $usersOnlineCache = [];

    public function __construct(
        private readonly Database $database,
        private readonly Date $date,
        private readonly User $user,
        private readonly ServiceConfig $serviceConfig,
    ) {
        $this->fetchUsersOnline();
    }

    /**
     * Returns a map of all users online with keys being user ID.
     *
     * @return array<int,array<int|string,null|int|string>>
     */
    public function getUsersOnline(): array
    {
        return $this->usersOnlineCache;
    }

    public function getGuestCount(): int
    {
        return $this->guestCount;
    }

    public function fetchUsersOnline(): void
    {
        $idletimeout = Carbon::now('UTC')
            ->subSeconds($this->serviceConfig->getSetting('timetoidle') ?? 300)
            ->getTimestamp()
        ;

        $guestCount = 0;

        $sessions = Session::selectMany(
            $this->database,
            'WHERE lastUpdate>=? ORDER BY lastAction',
            $this->database->datetime(Carbon::now('UTC')->subSeconds($this->serviceConfig->getSetting('timetologout') ?? 900)->getTimestamp()),
        );

        $members = Member::joinedOn($this->database, $sessions, static fn(Session $session): ?int => $session->uid);

        $today = gmdate('n j');

        foreach ($sessions as $session) {
            $member = $members[$session->uid] ?? null;

            if ($member === null) {
                ++$guestCount;

                continue;
            }

            if ($session->hide && !$this->user->isAdmin()) {
                continue;
            }

            $birthday = $member->birthdate && $this->date->dateAsCarbon($member->birthdate)->format('n j') === $today ? 1 : 0;
            $uid  = $session->isBot ? $session->id : $session->uid;

            $this->usersOnlineCache[$uid] = [
                'birthday' => $birthday,
                'uid' => $session->isBot ? $session->id : $session->uid,
                'name' => ($session->hide ? '* ' : '') . ($session->isBot ? $session->id : $member->displayName),
                'status' => $session->lastAction < $idletimeout
                    ? 'idle'
                    : 'active',
                'location' => $session->location,
                'locationVerbose' => $session->locationVerbose,
                'groupID' => $member->groupID,
            ];
        }

        $this->guestCount = $guestCount;
    }
}
