<?php

declare(strict_types=1);

namespace Tools\Mago;

final class Span
{
    public File $file_id;

    public TextRange $start;

    public TextRange $end;
}
