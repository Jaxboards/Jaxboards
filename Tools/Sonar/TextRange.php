<?php

declare(strict_types=1);

namespace Tools\Sonar;

final class TextRange
{
    public int $startLine;

    public int $endLine;

    public int $startColumn;

    public int $endColumn;
}
