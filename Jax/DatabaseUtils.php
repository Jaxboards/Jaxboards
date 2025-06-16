<?php

namespace Jax;

use Jax\Attributes\Column;
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

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $columnAttributes = $reflectionProperty->getAttributes(Column::class);

            $props = $columnAttributes[0]->newInstance();

            $name = $props->name;
            $type = $props->type;
            $length = $props->length !== 0 ? "({$props->length})" : '';
            $nullable = $props->nullable === false ? ' NOT NULL' : '';
            $unsigned = $props->unsigned === true ? ' unsigned' : '';
            $default = $props->default !== null ? " DEFAULT '{$props->default}'" : '';

            $fields[] = "  `{$name}` {$type}{$length}{$unsigned}{$nullable}{$default}";
        }

        return "CREATE TABLE $tableName (" . PHP_EOL
            . implode(',' . PHP_EOL, $fields) . PHP_EOL .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    public function render()
    {
        var_dump($this->createTableQueryFromModel(new Post()));
    }
}
