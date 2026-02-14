<?php

declare(strict_types=1);

namespace Jax\Database\Adapters;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Attributes\PrimaryKey;
use Jax\Database\Database;
use Jax\Database\Model;
use Override;
use ReflectionClass;

use function array_merge;
use function implode;

use const PHP_EOL;

final readonly class SQLite implements Adapter
{
    public function __construct(
        private Database $database,
    ) {}

    #[Override]
    public function createTableQueryFromModel(Model $model): string
    {
        $table = $model::TABLE;
        $tableQuoted = $this->database->ftable($table);
        $reflectionClass = new ReflectionClass($model::class);

        $fields = [];
        $keys = [];
        $constraints = [];

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $columnAttributes = $reflectionProperty->getAttributes(Column::class);

            if ($columnAttributes === []) {
                continue;
            }

            $columnAttribute = $columnAttributes[0]->newInstance();
            $fieldName = $this->database->quoteIdentifier($columnAttribute->name);
            $fields[] = $this->fieldDefinition($columnAttribute);

            $primaryKeyAttributes = $reflectionProperty->getAttributes(PrimaryKey::class);
            $foreignKeyAttributes = $reflectionProperty->getAttributes(ForeignKey::class);
            $keyAttributes = $reflectionProperty->getAttributes(Key::class);

            if ($foreignKeyAttributes !== []) {
                $foreignKey = $foreignKeyAttributes[0]->newInstance();
                $foreignField = $this->database->quoteIdentifier($foreignKey->field);
                $foreignTable = $this->database->ftable($foreignKey->table);
                $onDelete = match ($foreignKey->onDelete) {
                    'cascade' => ' ON DELETE CASCADE',
                    'null' => ' ON DELETE SET NULL',
                    default => '',
                };
                $keys[] = "KEY {$fieldName} ({$fieldName})";
                $constraints[] = "FOREIGN KEY ({$fieldName}) REFERENCES {$foreignTable} ({$foreignField}){$onDelete}";
            }

            if ($primaryKeyAttributes !== []) {
                $keys[] = "PRIMARY KEY ({$fieldName})";
            }

            if ($keyAttributes === []) {
                continue;
            }

            $keyAttribute = $keyAttributes[0]->newInstance();
            $fulltext = $keyAttribute->fulltext ? 'FULLTEXT ' : '';

            $keys[] = "{$fulltext}KEY {$fieldName} ({$fieldName})";
        }

        return (
            "CREATE TABLE {$tableQuoted} ("
            . PHP_EOL
            . '  '
            . implode(',' . PHP_EOL . '  ', array_merge(
                $fields,
                // $keys,
                $constraints,
            ))
            . PHP_EOL
            . ')'
        );
    }

    #[Override]
    public function install(): void {}

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

            case 'int':
                $type = 'integer';

                break;

            case 'string':
                $type = 'varchar';

            // no break
            default:
                break;
        }

        $length = $column->length !== 0 ? "({$column->length})" : '';
        $nullable = !$column->autoIncrement && !$column->nullable ? ' NOT NULL' : '';
        $autoIncrement = $column->autoIncrement ? ' PRIMARY KEY AUTOINCREMENT' : '';
        $default = $column->default !== null ? " DEFAULT '{$column->default}'" : '';

        return "{$fieldName} {$type}{$length}{$nullable}{$autoIncrement}{$default}";
    }
}
