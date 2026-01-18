<?php

declare(strict_types=1);

namespace Jax;

use function array_key_exists;

/**
 * An implementation of some of my favorite lodash functions.
 */
final class Lodash
{
    /**
     * @param array<mixed>                 $data
     * @param callable(mixed $data):string $iteratee
     *
     * @return array<int>
     */
    public static function countBy(array $data, callable $iteratee): array
    {
        $result = [];

        foreach ($data as $value) {
            $key = $iteratee($value);
            if (!array_key_exists($key, $result)) {
                $result[$key] = 0;
            }

            ++$result[$key];
        }

        return $result;
    }

    /**
     * @template T
     *
     * @param array<T>                     $data
     * @param callable(mixed $data):string $iteratee
     *
     * @return array<array<T>>
     */
    public static function groupBy(array $data, callable $iteratee): array
    {
        $result = [];

        foreach ($data as $value) {
            $key = $iteratee($value);
            if (!array_key_exists($key, $result)) {
                $result[$key] = [];
            }

            $result[$key][] = $value;
        }

        return $result;
    }

    /**
     * @template T
     *
     * @param array<T>                     $data
     * @param callable(mixed $data):string $iteratee
     *
     * @return array<T>
     */
    public static function keyBy(array $data, callable $iteratee): array
    {
        $result = [];

        foreach ($data as $value) {
            $result[$iteratee($value)] = $value;
        }

        return $result;
    }
}
