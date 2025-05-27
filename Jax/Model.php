<?php

declare(strict_types=1);

namespace Jax;

use PDO;

use function array_map;

abstract class Model
{
    public const FIELDS = [];

    public const TABLE = '';

    public function __construct(?array $properties = [])
    {
        foreach ($properties as $property => $value) {
            $this->{$property} = $value;
        }

        return $this;
    }

    public static function create(?array $properties): static
    {
        return new static($properties);
    }

    /**
     * @param mixed $args
     */
    public static function selectOne(Database $database, ...$args): ?static
    {
        $stmt = $database->select(
            array_map(
                static fn($field): string => "`{$field}`",
                static::FIELDS,
            ),
            static::TABLE,
            ...$args,
        );
        $record = $stmt?->fetchObject(static::class) ?: null;
        $database->disposeresult($stmt);

        return $record;
    }

    /**
     * @param mixed $args
     *
     * @return array<static>
     */
    public static function selectMany(Database $database, ...$args): array
    {
        $stmt = $database->select(
            array_map(
                static fn($field): string => "`{$field}`",
                static::FIELDS,
            ),
            static::TABLE,
            ...$args,
        );

        return $stmt?->fetchAll(PDO::FETCH_CLASS, static::class) ?? [];
    }

    public function asArray()
    {
        $data = [];
        foreach ($this::FIELDS as $fieldName) {
            $data[$fieldName] = $this->{$fieldName};
        }

        return $data;
    }
}
