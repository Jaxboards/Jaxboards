<?php

namespace Jax;

use Jax\Attributes\Column;
use Jax\Models\Post;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

class DatabaseUtils
{
    public function __construct(
        private Database $database,
    ) {}


    public function createTableQueryFromModel(Model $modelClass)
    {
        $tableName = $this->database->ftable($modelClass::TABLE);
        $reflectionClass = new ReflectionClass($modelClass::class);
        $attributes = array_merge(
            ...array_map(
                static fn(ReflectionProperty $reflectionProperty) => $reflectionProperty->getAttributes(Column::class),
                $reflectionClass->getProperties(),
            ),
        );

        $fields = implode(',' . PHP_EOL, array_map(
            function (ReflectionAttribute $reflectionAttribute) {
                $props = $reflectionAttribute->getArguments();
                var_dump($props);

                $name = $props['name'];
                $type = $props['type'];
                $length = array_key_exists('length', $props) &&$props['length'] !== 0 ? "({$props['length']})" : '';
                $nullable = array_key_exists('nullable', $props) && $props['nullable'] === false ? ' NOT NULL' : '';
                $unsigned = array_key_exists('unsigned', $props) && $props['unsigned'] === true ? ' unsigned' : '';
                $default = array_key_exists('default', $props) && $props['default'] !== null ? " DEFAULT '{$props['default']}'" : '';

                return "  `{$name}` {$type}{$length}{$unsigned}{$nullable}{$default}";
            },
            $attributes,
        ));

        return "CREATE TABLE $tableName (" . PHP_EOL
            . $fields . PHP_EOL .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    public function render()
    {
        var_dump($this->createTableQueryFromModel(new Post()));
    }
}
