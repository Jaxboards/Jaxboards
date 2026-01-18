<?php

declare(strict_types=1);

namespace Jax\Database\Adapters;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Database;
use Jax\Database\Model;
use ReflectionClass;

use function array_merge;
use function implode;

final readonly class MySQL implements Adapter
{
    public function __construct(private Database $database) {}

    public function createTableQueryFromModel(Model $model): string
    {
        $table = $model::TABLE;
        $tableQuoted = $this->database->ftable($table);
        $reflectionClass = new ReflectionClass($model::class);

        $fields = [];
        $keys = [];
        $constraints = [];

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $columnAttributes = $reflectionProperty->getAttributes(
                Column::class,
            );

            if ($columnAttributes === []) {
                continue;
            }

            $columnAttribute = $columnAttributes[0]->newInstance();
            $fieldName = $this->database->quoteIdentifier(
                $columnAttribute->name,
            );
            $fields[] = $this->fieldDefinition($columnAttribute);

            $primaryKeyAttributes = $reflectionProperty->getAttributes(
                PrimaryKey::class,
            );
            $foreignKeyAttributes = $reflectionProperty->getAttributes(
                ForeignKey::class,
            );
            $keyAttributes = $reflectionProperty->getAttributes(Key::class);

            if ($foreignKeyAttributes !== []) {
                $foreignKey = $foreignKeyAttributes[0]->newInstance();
                $foreignField = $this->database->quoteIdentifier(
                    $foreignKey->field,
                );
                $foreignTable = $this->database->ftable($foreignKey->table);
                $onDelete = match ($foreignKey->onDelete) {
                    'cascade' => 'ON DELETE CASCADE',
                    'null' => 'ON DELETE SET NULL',
                    default => '',
                };
                $keys[] = "KEY {$fieldName} ({$fieldName})";
                $constraintName = $this->database->quoteIdentifier(
                    "{$table}_fk_{$columnAttribute->name}",
                );
                $constraints[] = <<<SQL
                    CONSTRAINT {$constraintName}
                            FOREIGN KEY ({$fieldName})
                            REFERENCES {$foreignTable} ({$foreignField})
                            {$onDelete}
                    SQL;
            }

            if ($primaryKeyAttributes !== []) {
                $keys[] = "PRIMARY KEY ({$fieldName})";
            }

            if ($keyAttributes === []) {
                continue;
            }

            $keyAttribute = $keyAttributes[0]->newInstance();
            $keyType = match (true) {
                $keyAttribute->fulltext => 'FULLTEXT ',
                $keyAttribute->unique => 'UNIQUE ',
                default => '',
            };

            $keys[] = "{$keyType}KEY {$fieldName} ({$fieldName})";
        }

        return implode(
            "\n",
            [
                "CREATE TABLE {$tableQuoted} (",
                '    ' . implode(",\n    ", array_merge(
                    $fields,
                    $keys,
                    $constraints,
                )),
                ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            ],
        );
    }

    public function install(): void
    {
        $queries = [
            'SET foreign_key_checks = 0',
            "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'",
            "SET time_zone = '+00:00'",
        ];

        // Create tables
        foreach ($queries as $query) {
            $this->database->query($query);
        }
    }

    private function fieldDefinition(Column $column): string
    {
        $fieldName = $this->database->quoteIdentifier($column->name);
        $type = $column->type;

        switch ($type) {
            case 'bool':
                $type = 'tinyint(1)';
                $column->nullable = false;
                $column->unsigned = true;
                $column->default = 0;

                break;

            case 'string':
                $type = 'varchar';

                // no break
            default:
                break;
        }

        $length = $column->length !== 0 ? "({$column->length})" : '';
        $nullable = $column->nullable === false ? ' NOT NULL' : '';
        $autoIncrement = $column->autoIncrement ? ' AUTO_INCREMENT' : '';
        $unsigned = $column->unsigned ? ' unsigned' : '';
        $default = $column->default !== null
            ? " DEFAULT '{$column->default}'"
            : '';

        return "{$fieldName} {$type}{$length}{$unsigned}{$nullable}{$autoIncrement}{$default}";
    }
}
