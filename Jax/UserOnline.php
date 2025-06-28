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

    public string $location;

    public string $locationVerbose;

    public string $name;

    public int $readDate;

    public string $status;

    public int $uid;
}

// phpcs:enable
