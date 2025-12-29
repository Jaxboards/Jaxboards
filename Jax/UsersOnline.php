<?php

declare(strict_types=1);

namespace Jax;

use Carbon\Carbon;
use Jax\Constants\Groups;
use Jax\Database\Database;
use Jax\Models\Member;
use Jax\Models\Session;

use function array_filter;
use function array_map;
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
        private readonly Config $config,
        private readonly Database $database,
        private readonly Date $date,
        private readonly Router $router,
        private readonly User $user,
        private readonly ServiceConfig $serviceConfig,
    ) {
        $this->idleTimestamp = Carbon::now('UTC')
            ->subSeconds($this->serviceConfig->getSetting('timetoidle') ?? 300)
            ->getTimestamp();
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
            'WHERE lastUpdate>=? ORDER BY lastAction',
            $this->database->datetime(Carbon::now('UTC')->subSeconds($this->serviceConfig->getSetting('timetologout') ?? 900)->getTimestamp()),
        );

        $userSessions = array_filter($sessions, static fn(Session $session): bool => (bool) $session->uid);
        $guestCount = count($sessions) - count($userSessions);

        $this->guestCount = $guestCount;

        $this->usersOnlineCache = $this->sessionsToUsersOnline($sessions);
    }

    /**
     * @return array<UserOnline>
     */
    public function getUsersOnlineToday(): array
    {
        $sessions = Session::selectMany(
            'WHERE uid AND hide = 0',
        );

        return array_map(function (UserOnline $userOnline): UserOnline {
            $userOnline->lastOnlineRelative = $this->date->relativeTime($userOnline->getLastOnline());

            return $userOnline;
        }, $this->sessionsToUsersOnline($sessions));
    }

    /**
     * @param array<Session> $sessions
     *
     * @return array<UserOnline>
     */
    private function sessionsToUsersOnline(array $sessions): array
    {
        $members = Member::joinedOn($sessions, static fn(Session $session): ?int => $session->uid);

        $today = gmdate('n j');

        $usersOnline = [];
        foreach ($sessions as $session) {
            $member = $members[$session->uid] ?? null;

            if ($session->hide && !$this->user->isAdmin()) {
                continue;
            }

            $birthday = !$session->isBot
                && $member?->birthdate
                && $this->config->getSetting('birthdays')
                && $this->date->dateAsCarbon($member->birthdate)?->format('n j') === $today;

            $uid = $session->isBot ? $session->id : $session->uid;
            $name = ($session->isBot ? $session->id : $member?->displayName);

            if (!$name) {
                continue;
            }

            $userOnline = new UserOnline();

            $userOnline->birthday = $birthday;
            $userOnline->groupID = $member->groupID ?? Groups::Guest->value;
            $userOnline->hide = (bool) $session->hide;
            $userOnline->isBot = (bool) $session->isBot;
            $userOnline->lastAction = $this->date->datetimeAsTimestamp($session->lastAction);
            $userOnline->lastUpdate = $this->date->datetimeAsTimestamp($session->lastUpdate);
            $userOnline->location = $session->location;
            $userOnline->locationVerbose = $session->locationVerbose;
            $userOnline->name = ($session->hide ? '* ' : '') . $name;
            $userOnline->profileURL = $session->isBot
                ? null
                : $this->router->url('profile', ['id' => $uid]);
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
