<?php

declare(strict_types=1);

namespace Tools\Mago;

final class LintRule
{
    public string $name;

    public string $code;

    public string $description;

    public string $good_example;

    public string $bad_example;

    public string $category;
}
