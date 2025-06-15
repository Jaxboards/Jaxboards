<?php

namespace Jax\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Column
{
    public function __construct(
        public string $name,
        public string $type,
        public int $length,
        public bool $nullable = true,
        public mixed $default,
        public bool $autoIncrement = false,
        public bool $unsigned = false,
    ) {}
}
