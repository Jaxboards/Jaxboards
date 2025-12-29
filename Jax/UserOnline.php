<?php

declare(strict_types=1);

namespace Jax;

// phpcs:disable SlevomatCodingStandard.Classes.ForbiddenPublicProperty
final class UserOnline
{
    public bool $birthday;

    public int $groupID;

    public bool $hide;

    public bool $isBot;

    public int $lastAction;

    public int $lastUpdate;

    public string $lastOnlineRelative;

    public string $location;

    public string $locationVerbose;

    public string $name;

    public ?string $profileURL;

    public int $readDate;

    public string $status;

    public int|string|null $uid = null;

    public function getLastOnline(): int
    {
        return $this->hide ? $this->readDate : $this->lastUpdate;
    }
}

// phpcs:enable
