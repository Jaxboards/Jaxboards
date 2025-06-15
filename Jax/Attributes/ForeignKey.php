<?php

declare(strict_types=1);

namespace Jax\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ForeignKey
{
    public function __construct(
        public string $table,
        public string $field,
        public ?string $onDelete = null,
    ) {}
}
