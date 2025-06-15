<?php

declare(strict_types=1);

namespace Jax\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Column
{
    public function __construct(
        public string $name,
        public string $type,
        public mixed $default = null,
        public int $length = 0,
        public bool $nullable = true,
        public bool $autoIncrement = false,
        public bool $unsigned = false,
    ) {}
}
