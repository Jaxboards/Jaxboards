<?php

declare(strict_types=1);

namespace Tools\Sonar;

final class Issue
{
    public string $ruleId;

    public int $effortMinutes;

    public Location $primaryLocation;
}
