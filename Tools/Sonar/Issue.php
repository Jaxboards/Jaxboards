<?php

declare(strict_types=1);

namespace Tools\Sonar;

class Issue
{
    public string $ruleID;

    public int $effortMinutes;

    public Location $primaryLocation;
}
