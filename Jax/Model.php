<?php

namespace Jax;

use PDO;

class Model {
    const fields = [];
    const table = '';

    /**
     * @param mixed $args
     */
    static function selectOne(Database $database, ...$args): ?static
    {
        $stmt = $database->select(
            array_map(
                fn($field) => "`{$field}`",
                static::fields,
            ),
            static::table,
            ...$args
        );
        $record = $stmt?->fetchObject(static::class) ?: null;
        $database->disposeresult($stmt);

        return $record;
    }

    /**
     * @param mixed $args
     * @return Array<static>
     */
    static function selectAll(Database $database, ...$args): array
    {
        $stmt = $database->select(
            array_map(
                fn($field) => "`{$field}`",
                static::fields,
            ),
            static::table,
            ...$args
        );

        return $stmt?->fetchAll(PDO::FETCH_CLASS, static::class) ?? [];
    }
}
