<?php

declare(strict_types=1);

namespace Jax\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Key
{
    public function __construct(public bool $fulltext = false) {}
}
