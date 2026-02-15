<?php

declare(strict_types=1);

namespace Tools\Sonar;

class Location
{
    public string $message;

    public string $filePath;

    public TextRange $textRange;
}
