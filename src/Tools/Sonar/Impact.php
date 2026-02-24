<?php

declare(strict_types=1);

namespace Tools\Sonar;

final class Impact
{
    public string $softwareQuality;

    //  BLOCKER, HIGH, MEDIUM, LOW, INFO
    public string $severity;
}
