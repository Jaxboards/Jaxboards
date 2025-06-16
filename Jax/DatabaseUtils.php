<?php

declare(strict_types=1);

namespace Jax;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Attributes\PrimaryKey;
use Jax\Models\Activity;
use Jax\Models\Category;
use Jax\Models\File;
use Jax\Models\Forum;
use Jax\Models\Group;
use Jax\Models\Member;
use Jax\Models\Message;
use Jax\Models\Page;
use Jax\Models\Post;
use Jax\Models\ProfileComment;
use Jax\Models\RatingNiblet;
use Jax\Models\Session;
use Jax\Models\Shout;
use Jax\Models\Skin;
use Jax\Models\Stats;
use Jax\Models\TextRule;
use Jax\Models\Token;
use Jax\Models\Topic;
use ReflectionClass;

use function array_merge;
use function implode;

use const PHP_EOL;

final readonly class DatabaseUtils
{
    public const TABLES = [
        Page::class,
        Skin::class,
        TextRule::class,
        Category::class,
        Group::class,
        Member::class,
        Token::class,
        File::class,
        Message::class,
        Topic::class,
        Post::class,
        Forum::class,
        Activity::class,
        ProfileComment::class,
        RatingNiblet::class,
        Session::class,
        Shout::class,
        Stats::class,
    ];

    // Roughly topologically sorted
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
                $constraintName = $this->database->quoteIdentifier("{$table}_fk_{$columnAttribute->name}");
                $constraints[] = "CONSTRAINT {$constraintName} FOREIGN KEY ({$fieldName}) REFERENCES {$foreignTable} ({$foreignField}){$onDelete}";
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

        return "CREATE TABLE {$tableQuoted} (" . PHP_EOL
            . '  ' . implode(',' . PHP_EOL . '  ', array_merge(
                $fields,
                $keys,
                $constraints,
            )) . PHP_EOL
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    public function install(): string
    {
        $createTableQueries = [];

        $header = <<<'SQL'
            SET foreign_key_checks = 0;
            SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
            SET time_zone = '+00:00';
            SQL;

        foreach ($this::TABLES as $tableClass) {
            $createTableQueries[] = $this->createTableQueryFromModel(new $tableClass());
        }

        return $header . implode(';' . PHP_EOL, $createTableQueries) . ';';
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
