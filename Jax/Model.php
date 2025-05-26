<?php

namespace Jax;

use PDO;

use function array_map;

abstract class Model {
    public const FIELDS = [];
    public const TABLE = '';

    /**
     * @param mixed $args
     */
    public static function selectOne(Database $database, ...$args): ?static
    {
        $stmt = $database->select(
            array_map(
                static fn($field) => "`{$field}`",
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
     * @return Array<static>
     */
    public static function selectAll(Database $database, ...$args): array
    {
        $stmt = $database->select(
            array_map(
                static fn($field) => "`{$field}`",
                static::FIELDS,
            ),
            static::TABLE,
            ...$args,
        );

        return $stmt?->fetchAll(PDO::FETCH_CLASS, static::class) ?? [];
    }
}
