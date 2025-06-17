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
    public const MODELS = [
        Activity::class,
        Category::class,
        File::class,
        Forum::class,
        Group::class,
        Member::class,
        Message::class,
        Page::class,
        Post::class,
        ProfileComment::class,
        RatingNiblet::class,
        Session::class,
        Shout::class,
        Skin::class,
        Stats::class,
        TextRule::class,
        Token::class,
        Topic::class,
    ];

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

    public function install(): void
    {
        $queries = [
            'SET foreign_key_checks = 0',
            "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'",
            "SET time_zone = '+00:00'",
        ];

        foreach ($this::MODELS as $modelClass) {
            $model = new $modelClass();
            $queries[] = 'DROP TABLE IF EXISTS ' . $this->database->ftable($model::TABLE);
            $queries[] = $this->createTableQueryFromModel($model);
        }

        // Create tables
        foreach ($queries as $query) {
            $this->database->query($query);
        }

        $this->insertInitialRecords();
    }

    private function insertInitialRecords(): void
    {
        $category = new Category();
        $category->id = 1;
        $category->title = 'Category';
        $category->insert($this->database);

        $forum = new Forum();
        $forum->id = 1;
        $forum->category = 1;
        $forum->title = 'Forum';
        $forum->subtitle = 'Your very first forum!';
        $forum->lastPostUser = 1;
        $forum->lastPostDate = $this->database->datetime();
        $forum->lastPostTopic = 1;
        $forum->lastPostTopicTitle = 'Welcome to Jaxboards!';
        $forum->topics = 1;
        $forum->posts = 1;
        $forum->insert($this->database);

        $member = new Group();
        $member->id = 1;
        $member->title = 'Member';
        $member->canPost = 1;
        $member->canEditPosts = 1;
        $member->canCreateTopics = 1;
        $member->canEditTopics = 1;
        $member->canAddComments = 0;
        $member->canDeleteComments = 0;
        $member->canViewBoard = 1;
        $member->canViewOfflineBoard = 0;
        $member->floodControl = 0;
        $member->canOverrideLockedTopics = 0;
        $member->icon = '';
        $member->canShout = 1;
        $member->canModerate = 0;
        $member->canDeleteShouts = 0;
        $member->canDeleteOwnShouts = 0;
        $member->canKarma = 1;
        $member->canIM = 1;
        $member->canPM = 1;
        $member->canLockOwnTopics = 0;
        $member->canDeleteOwnTopics = 0;
        $member->canUseSignatures = 1;
        $member->canAttach = 0;
        $member->canDeleteOwnPosts = 0;
        $member->canPoll = 0;
        $member->canAccessACP = 0;
        $member->canViewShoutbox = 1;
        $member->canViewStats = 1;
        $member->legend = 0;
        $member->canViewFullProfile = 0;
        $member->insert($this->database);

        $admin = new Group();
        $admin->id = 2;
        $admin->title = 'Admin';
        $admin->canPost = 1;
        $admin->canEditPosts = 1;
        $admin->canCreateTopics = 1;
        $admin->canEditTopics = 1;
        $admin->canAddComments = 1;
        $admin->canDeleteComments = 1;
        $admin->canViewBoard = 1;
        $admin->canViewOfflineBoard = 1;
        $admin->floodControl = 0;
        $admin->canOverrideLockedTopics = 1;
        $admin->icon = '';
        $admin->canShout = 1;
        $admin->canModerate = 1;
        $admin->canDeleteShouts = 1;
        $admin->canDeleteOwnShouts = 1;
        $admin->canKarma = 1;
        $admin->canIM = 1;
        $admin->canPM = 1;
        $admin->canLockOwnTopics = 1;
        $admin->canDeleteOwnTopics = 1;
        $admin->canUseSignatures = 1;
        $admin->canAttach = 0;
        $admin->canDeleteOwnPosts = 0;
        $admin->canPoll = 0;
        $admin->canAccessACP = 1;
        $admin->canViewShoutbox = 1;
        $admin->canViewStats = 1;
        $admin->legend = 0;
        $admin->canViewFullProfile = 0;
        $admin->insert($this->database);

        $guest = new Group();
        $guest->id = 3;
        $guest->title = 'Guest';
        $guest->canPost = 0;
        $guest->canEditPosts = 0;
        $guest->canCreateTopics = 0;
        $guest->canEditTopics = 0;
        $guest->canAddComments = 0;
        $guest->canDeleteComments = 0;
        $guest->canViewBoard = 1;
        $guest->canViewOfflineBoard = 0;
        $guest->floodControl = 0;
        $guest->canOverrideLockedTopics = 0;
        $guest->icon = '';
        $guest->canShout = 0;
        $guest->canModerate = 0;
        $guest->canDeleteShouts = 0;
        $guest->canDeleteOwnShouts = 0;
        $guest->canKarma = 0;
        $guest->canIM = 0;
        $guest->canPM = 0;
        $guest->canLockOwnTopics = 0;
        $guest->canDeleteOwnTopics = 0;
        $guest->canUseSignatures = 0;
        $guest->canAttach = 0;
        $guest->canDeleteOwnPosts = 0;
        $guest->canPoll = 0;
        $guest->canAccessACP = 0;
        $guest->canViewShoutbox = 1;
        $guest->canViewStats = 1;
        $guest->legend = 0;
        $guest->canViewFullProfile = 0;
        $guest->insert($this->database);

        $banned = new Group();
        $banned->id = 4;
        $banned->title = 'Banned';
        $banned->canPost = 0;
        $banned->canEditPosts = 0;
        $banned->canCreateTopics = 0;
        $banned->canEditTopics = 0;
        $banned->canAddComments = 0;
        $banned->canDeleteComments = 0;
        $banned->canViewBoard = 0;
        $banned->canViewOfflineBoard = 0;
        $banned->floodControl = 0;
        $banned->canOverrideLockedTopics = 0;
        $banned->icon = '';
        $banned->canShout = 0;
        $banned->canModerate = 0;
        $banned->canDeleteShouts = 0;
        $banned->canDeleteOwnShouts = 0;
        $banned->canKarma = 0;
        $banned->canIM = 0;
        $banned->canPM = 0;
        $banned->canLockOwnTopics = 0;
        $banned->canDeleteOwnTopics = 0;
        $banned->canUseSignatures = 0;
        $banned->canAttach = 0;
        $banned->canDeleteOwnPosts = 0;
        $banned->canPoll = 0;
        $banned->canAccessACP = 0;
        $banned->canViewShoutbox = 0;
        $banned->canViewStats = 0;
        $banned->legend = 0;
        $banned->canViewFullProfile = 0;
        $banned->insert($this->database);

        $validating = new Group();
        $validating->id = 5;
        $validating->title = 'Validating';
        $validating->canPost = 0;
        $validating->canEditPosts = 0;
        $validating->canCreateTopics = 0;
        $validating->canEditTopics = 0;
        $validating->canAddComments = 0;
        $validating->canDeleteComments = 0;
        $validating->canViewBoard = 1;
        $validating->canViewOfflineBoard = 0;
        $validating->floodControl = 0;
        $validating->canOverrideLockedTopics = 0;
        $validating->icon = '';
        $validating->canShout = 0;
        $validating->canModerate = 0;
        $validating->canDeleteShouts = 0;
        $validating->canDeleteOwnShouts = 0;
        $validating->canKarma = 0;
        $validating->canIM = 0;
        $validating->canPM = 0;
        $validating->canLockOwnTopics = 0;
        $validating->canDeleteOwnTopics = 0;
        $validating->canUseSignatures = 0;
        $validating->canAttach = 0;
        $validating->canDeleteOwnPosts = 0;
        $validating->canPoll = 0;
        $validating->canAccessACP = 0;
        $validating->canViewShoutbox = 1;
        $validating->canViewStats = 1;
        $validating->legend = 0;
        $validating->canViewFullProfile = 0;
        $validating->insert($this->database);

        $post = new Post();
        $post->id = 1;
        $post->author = 1;
        $post->post = <<<'POST'
            Now, it's only a matter of time before you have everything set up.
            You'll find everything you need to get started in the ACP (link at the top).

            Enjoy your forum!
            POST;
        $post->tid = 1;
        $post->newtopic = 1;
        $post->insert($this->database);

        $skin = new Skin();
        $skin->id = 1;
        $skin->using = 1;
        $skin->title = 'Default';
        $skin->custom = 0;
        $skin->wrapper = 'Default';
        $skin->default = 1;
        $skin->insert($this->database);

        $stats = new Stats();
        $stats->id = 1;
        $stats->posts = 1;
        $stats->topics = 1;
        $stats->members = 1;
        $stats->most_members = 1;
        $stats->most_members_day = 1;
        $stats->last_register = 1;
        $stats->dbVersion = 4;
        $stats->insert($this->database);

        $topic = new Topic();
        $topic->id = 1;
        $topic->title = 'Welcome to Jaxboards!';
        $topic->subtitle = 'Your support is appreciated.';
        $topic->lastPostUser = 1;
        $topic->fid = 1;
        $topic->author = 1;
        $topic->summary = " Now, it's only a matter of time";
        $topic->op = 1;
        $topic->insert($this->database);
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
