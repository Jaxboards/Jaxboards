<?php

declare(strict_types=1);

namespace Jax;

use Jax\Attributes\Column;
use Jax\Attributes\PrimaryKey;
use PDO;
use PDOStatement;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

use function _\keyBy;
use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;

use const SORT_REGULAR;

abstract class Model
{
    public const TABLE = '';

    private bool $fromDatabase = false;

    public function __construct()
    {
        $primaryKey = static::getPrimaryKey();

        if ($primaryKey !== '' && !$this->{$primaryKey}) {
            return;
        }

        $this->fromDatabase = true;
    }

    public static function getPrimaryKey()
    {
        $reflectionClass = new ReflectionClass(static::class);

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $maybePrimaryKeys = $reflectionProperty->getAttributes(PrimaryKey::class);
            if ($maybePrimaryKeys !== []) {
                [$column] = $reflectionProperty->getAttributes(Column::class);
                return $column->newInstance()->name;
            }
        }

        return '';
    }

    public static function getFields()
    {
        $reflectionClass = new ReflectionClass(static::class);
        $attributes = array_merge(
            ...array_map(
                static fn(ReflectionProperty $reflectionProperty) => $reflectionProperty->getAttributes(Column::class),
                $reflectionClass->getProperties(),
            ),
        );

        return array_map(
            static fn(ReflectionAttribute $reflectionAttribute) => $reflectionAttribute->getArguments()['name'],
            $attributes,
        );
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
                $database->quoteIdentifier(...),
                static::getFields(),
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
                static::getFields(),
            ),
            static::TABLE,
            ...$args,
        );

        return $stmt?->fetchAll(PDO::FETCH_CLASS, static::class) ?? [];
    }

    /**
     * Given a list of $otherModels, fetches models with the ID given by $getId($otherModel).
     *
     * @param array<Model> $otherModel
     *
     * @return array<static> A map of models by ID (array key is ID)
     */
    public static function joinedOn(
        Database $database,
        array $otherModel,
        callable $getId,
        ?string $key = null,
    ): array {
        $primaryKey = static::getPrimaryKey();
        $key ??= $primaryKey;

        $otherIds = array_unique(
            array_filter(
                array_map($getId, $otherModel),
                static fn($otherId): bool => $otherId !== null,
            ),
            SORT_REGULAR,
        );

        return $otherIds !== [] ? keyBy(
            static::selectMany(
                $database,
                "WHERE {$key} IN ?",
                $otherIds,
            ),
            static fn($member): int => $member->{$primaryKey},
        ) : $otherIds;
    }

    public function delete(Database $database): ?PDOStatement
    {
        $primaryKey = static::getPrimaryKey();

        return $database->delete(
            static::TABLE,
            "WHERE {$primaryKey}=?",
            $this->{$primaryKey},
        );
    }

    public function insert(Database $database): ?PDOStatement
    {
        $primaryKey = static::getPrimaryKey();
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
        $primaryKey = static::getPrimaryKey();
        $data = $this->asArray();

        return $database->update(
            static::TABLE,
            $this->asArray(),
            ...($primaryKey !== '' ? [
                "WHERE {$primaryKey}=?",
                $data[static::getPrimaryKey()],
            ] : []),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function asArray(): array
    {
        $data = [];
        foreach (static::getFields() as $fieldName) {
            $data[$fieldName] = $this->{$fieldName};
        }

        return $data;
    }
}
