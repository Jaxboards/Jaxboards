<?php

declare(strict_types=1);

namespace Jax;

use PDO;
use PDOStatement;
use ReflectionProperty;

use function array_map;

abstract class Model
{
    public const FIELDS = [];

    public const TABLE = '';

    public const PRIMARY_KEY = 'id';

    private bool $fromDatabase = false;

    public function __construct()
    {
        $primaryKey = static::PRIMARY_KEY;

        if ($primaryKey != '' && !$this->{$primaryKey}) {
            return;
        }

        $this->fromDatabase = true;
    }

    /**
     * @param mixed $args
     */
    public static function count(Database $database, ...$args): ?int
    {
        $stmt = $database->select(
            'COUNT(*) as `count`',
            static::TABLE,
            ...$args,
        );
        $result = $stmt?->fetch(PDO::FETCH_OBJ);

        return $result?->count;
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
     * @return array<int,static>
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
        $primaryKey = static::PRIMARY_KEY;

        return $database->delete(
            static::TABLE,
            "WHERE {$primaryKey}=?",
            $this->{$primaryKey},
        );
    }

    public function insert(Database $database): ?PDOStatement
    {
        $primaryKey = static::PRIMARY_KEY;
        $reflectionProperty = new ReflectionProperty(static::class, $primaryKey);
        $type = (string) $reflectionProperty->getType();
        $statement = $database->insert(static::TABLE, $this->asArray());
        $insertId = $database->insertId();

        if ($insertId) {
            $this->{$primaryKey} = $type === 'string'
                ? $insertId
                : (int) $insertId;
        }

        return $statement;
    }

    public function upsert(Database $database): ?PDOStatement
    {
        if ($this->fromDatabase) {
            return $this->update($database);
        }

        return $this->insert($database);
    }

    public function update(Database $database): ?PDOStatement
    {
        $primaryKey = static::PRIMARY_KEY;
        $data = $this->asArray();

        return $database->update(
            static::TABLE,
            $this->asArray(),
            ...($primaryKey !== '' ? [
                "WHERE {$primaryKey}=?",
                $data[static::PRIMARY_KEY],
            ] : [])
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
