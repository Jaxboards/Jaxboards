<?php

namespace Tools\Sonar;

class Rule
{
    public string $id;
    public string $name;
    public string $description;
    public string $engineId;
    public string $cleanCodeAttribute;
    public ?string $type;
    public ?string $severity = 'MEDIUM';

    /**
     * @var array<SonarImpact>
     */
    public array $impacts = [];
}
