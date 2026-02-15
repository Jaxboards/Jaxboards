<?php

declare(strict_types=1);

namespace Tools\Sonar;

class Rule
{
    public string $id;

    public string $name;

    public string $description;

    public string $engineId;

    public string $cleanCodeAttribute;

    public ?string $type = null;

    public ?string $severity = 'MEDIUM';

    /**
     * @var array<SonarImpact>
     */
    public array $impacts = [];
}
