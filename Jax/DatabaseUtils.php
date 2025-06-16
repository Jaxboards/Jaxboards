<?php

namespace Jax;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Models\Post;
use ReflectionClass;

class DatabaseUtils
{
    public function __construct(
        private Database $database,
    ) {}


    public function createTableQueryFromModel(Model $modelClass)
    {
        $tableName = $this->database->ftable($modelClass::TABLE);
        $reflectionClass = new ReflectionClass($modelClass::class);

        $fields = [];
        $keys = [];
        $constraints = [];

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $columnAttributes = $reflectionProperty->getAttributes(Column::class);

            if ($columnAttributes === []) {
                continue;
            }

            $props = $columnAttributes[0]->newInstance();

            $fieldName = $this->database->quoteIdentifier($props->name);
            $type = $props->type;
            $length = $props->length !== 0 ? "({$props->length})" : '';
            $nullable = $props->nullable === false ? ' NOT NULL' : '';
            $unsigned = $props->unsigned === true ? ' unsigned' : '';
            $default = $props->default !== null ? " DEFAULT '{$props->default}'" : '';

            $fields[] = "{$fieldName} {$type}{$length}{$unsigned}{$nullable}{$default}";

            $foreignKeyAttributes = $reflectionProperty->getAttributes(ForeignKey::class);
            $keyAttributes = $reflectionProperty->getAttributes(Key::class);

            if ($foreignKeyAttributes !== []) {
                $foreignKey = $foreignKeyAttributes[0]->newInstance();
                $foreignField = $this->database->quoteIdentifier($foreignKey->field);
                $foreignTable = $this->database->ftable($foreignKey->table);
                $onDelete = match($foreignKey->onDelete) {
                    'cascade' => ' ON DELETE CASCADE',
                    'null' => ' ON DELETE SET NULL',
                    default => ''
                };
                $keys[] = "KEY {$fieldName} ({$fieldName})";
                $constraints[] = "CONSTRAINT {$fieldName} FOREIGN KEY ({$fieldName}) REFERENCES $foreignTable ($foreignField){$onDelete}";
            }

            if ($keyAttributes !== []) {
                $keys[] = "KEY {$fieldName} ({$fieldName})";
            }

        }

        return "CREATE TABLE $tableName (" . PHP_EOL
            . '  ' . implode(',' . PHP_EOL . '  ', array_merge(
                $fields,
                $keys,
                $constraints
            )) . PHP_EOL .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    public function render()
    {
        var_dump($this->createTableQueryFromModel(new Post()));
    }
}
