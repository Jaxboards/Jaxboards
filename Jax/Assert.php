<?php

declare(strict_types=1);

namespace Jax;

use function array_diff_assoc;
use function assert;
use function json_encode;

final class Assert
{
    public function equals(mixed $expected, mixed $result): void
    {
        assert($expected === $result, "Expected {$expected} to match {$result}");
    }

    public function deepEquals(array $expected, array $result): void
    {
        $diff = array_diff_assoc($expected, $result);

        assert($diff === [], 'Expected value differs: ' . json_encode($diff));
    }
}
