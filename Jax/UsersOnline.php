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
     * @var array<UserOnline>
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
     * @return array<UserOnline>
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

        $userSessions = array_filter($sessions, static fn(Session $session): bool => (bool) $session->uid);
        $guestCount = count($sessions) - count($userSessions);

        $this->guestCount = $guestCount;

        $this->usersOnlineCache = $this->sessionsToUsersOnline($userSessions);
    }

    /**
     * @return array<UserOnline>
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
     * @return array<UserOnline>
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

            $birthday = $member->birthdate && $this->date->dateAsCarbon($member->birthdate)?->format('n j') === $today;
            $uid = $session->isBot ? $session->id : $session->uid;

            $userOnline = new UserOnline();

            $userOnline->birthday = $birthday;
            $userOnline->isBot = (bool) $session->isBot;
            $userOnline->groupID = $member->groupID;
            $userOnline->hide = (bool) $session->hide;
            $userOnline->lastAction = $this->date->datetimeAsTimestamp($session->lastAction);
            $userOnline->lastUpdate = $this->date->datetimeAsTimestamp($session->lastUpdate);
            $userOnline->location = $session->location;
            $userOnline->locationVerbose = $session->locationVerbose;
            $userOnline->name = ($session->hide ? '* ' : '') . ($session->isBot ? $session->id : $member->displayName);
            $userOnline->readDate = $this->date->datetimeAsTimestamp($session->readDate);
            $userOnline->status = $session->lastAction < $this->idleTimestamp
                    ? 'idle'
                    : 'active';
            $userOnline->uid = $uid;

            $usersOnline[$uid] = $userOnline;
        }

        return $usersOnline;
    }
}
