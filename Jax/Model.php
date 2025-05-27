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
    public $PRIMARY_KEY;

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

    public function update(Database $database): ?PDOStatement
    {
        $data = $this->asArray();

        return $database->update(
            $this::TABLE,
            $this->asArray(),
            Database::WHERE_ID_EQUALS,
            $data[$this::PRIMARY_KEY],
        );
    }

    public function insert(Database $database): ?PDOStatement
    {
        $statement = $database->insert($this::TABLE, $this->asArray());
        $this->{$this::PRIMARY_KEY} = (int) $database->insertId();

        return $statement;
    }

    public function upsert(Database $database): ?PDOStatement
    {
        if ($this->{$this::PRIMARY_KEY}) {
            return $this->update($database);
        }

        return $this->insert($database);
    }

    /**
     * @return array<string,mixed>
     */
    public function asArray(): array
    {
        $data = [];
        foreach ($this::FIELDS as $fieldName) {
            $data[$fieldName] = $this->{$fieldName};
        }

        return $data;
    }
}
