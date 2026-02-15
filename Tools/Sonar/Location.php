<?php

declare(strict_types=1);

namespace Tools\Sonar;

final class Location
{
    public string $message;

    public string $filePath;

    public TextRange $textRange;
}
