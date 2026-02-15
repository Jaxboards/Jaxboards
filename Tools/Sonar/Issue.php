<?php

namespace Tools\Sonar;

class Issue
{
    public string $ruleID;
    public int $effortMinutes;
    public Location $primaryLocation;
}
