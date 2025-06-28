<?php

declare(strict_types=1);

namespace Jax;

use Carbon\Carbon;
use Jax\Models\Member;
use Jax\Models\Session;

use function array_filter;
use function count;
use function gmdate;

final class UsersOnline
{
    private int $guestCount = 0;

    private readonly int $idleTimestamp;

    /**
     * @var array<array<int|string,null|int|string>>
     */
    private array $usersOnlineCache = [];

    public function __construct(
        private readonly Database $database,
        private readonly Date $date,
        private readonly User $user,
        private readonly ServiceConfig $serviceConfig,
    ) {
        $this->idleTimestamp = Carbon::now('UTC')
            ->subSeconds($this->serviceConfig->getSetting('timetoidle') ?? 300)
            ->getTimestamp()
        ;
        $this->fetchUsersOnline();
    }

    /**
     * Returns a map of all users online with keys being user ID.
     *
     * @return array<array<int|string,null|int|string>>
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
        $sessions = Session::selectMany(
            $this->database,
            'WHERE lastUpdate>=? ORDER BY lastAction',
            $this->database->datetime(Carbon::now('UTC')->subSeconds($this->serviceConfig->getSetting('timetologout') ?? 900)->getTimestamp()),
        );

        $userSessions = array_filter($sessions, static fn(Session $session): ?int => $session->uid);
        $guestCount = count($sessions) - count($userSessions);

        $this->guestCount = $guestCount;

        $this->usersOnlineCache = $this->sessionsToUsersOnline($userSessions);
    }

    /**
     * @return array<array<int|string,null|int|string>>
     */
    public function getUsersOnlineToday(): array
    {
        $sessions = Session::selectMany(
            $this->database,
            'WHERE uid AND hide = 0',
        );

        return $this->sessionsToUsersOnline($sessions);
    }

    /**
     * @param array<Session> $sessions
     *
     * @return array<array<int|string,null|int|string>>
     */
    private function sessionsToUsersOnline(array $sessions): array
    {
        $members = Member::joinedOn($this->database, $sessions, static fn(Session $session): ?int => $session->uid);

        $today = gmdate('n j');

        $usersOnline = [];
        foreach ($sessions as $session) {
            $member = $members[$session->uid] ?? null;

            if ($member === null) {
                continue;
            }

            if ($session->hide && !$this->user->isAdmin()) {
                continue;
            }

            $birthday = $member->birthdate && $this->date->dateAsCarbon($member->birthdate)?->format('n j') === $today
                ? 1
                : 0;
            $uid = $session->isBot ? $session->id : $session->uid;

            $usersOnline[$uid] = [
                'birthday' => $birthday,
                'groupID' => $member->groupID,
                'hide' => $session->hide,
                'lastAction' => $this->date->datetimeAsTimestamp($session->lastAction),
                'lastUpdate' => $this->date->datetimeAsTimestamp($session->lastUpdate),
                'location' => $session->location,
                'locationVerbose' => $session->locationVerbose,
                'name' => ($session->hide ? '* ' : '') . ($session->isBot ? $session->id : $member->displayName),
                'readDate' => $this->date->datetimeAsTimestamp($session->readDate),
                'status' => $session->lastAction < $this->idleTimestamp
                    ? 'idle'
                    : 'active',
                'uid' => $session->isBot ? $session->id : $session->uid,
            ];
        }

        return $usersOnline;
    }
}
