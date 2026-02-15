<?php

declare(strict_types=1);

namespace Tools\Mago;

class Lint
{
    public string $level;

    public string $code;

    public string $message;

    /**
     * @var Array<string> $notes
     */
    public array $notes;

    public string $help;

    /**
     * @var Array<Annotation> $annotations
     */
    public array $annotations;
}
