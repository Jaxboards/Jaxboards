<?php

declare(strict_types=1);

namespace Jax;

use PDO;
use PDOStatement;

use function array_map;

abstract class Model
{
    public const FIELDS = [];

    public const TABLE = '';

    public const PRIMARY_KEY = 'id';

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

    public function delete(Database $database): ?PDOStatement
    {
        return $database->delete(
            static::TABLE,
            Database::WHERE_ID_EQUALS,
            $this->{static::PRIMARY_KEY},
        );
    }

    public function insert(Database $database): ?PDOStatement
    {
        $statement = $database->insert(static::TABLE, $this->asArray());
        $this->{static::PRIMARY_KEY} = (int) $database->insertId();

        return $statement;
    }

    public function upsert(Database $database): ?PDOStatement
    {
        if ($this->{static::PRIMARY_KEY}) {
            return $this->update($database);
        }

        return $this->insert($database);
    }

    public function update(Database $database): ?PDOStatement
    {
        $data = $this->asArray();

        return $database->update(
            static::TABLE,
            $this->asArray(),
            Database::WHERE_ID_EQUALS,
            $data[static::PRIMARY_KEY],
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function asArray(): array
    {
        $data = [];
        foreach (static::FIELDS as $fieldName) {
            $data[$fieldName] = $this->{$fieldName};
        }

        return $data;
    }
}
