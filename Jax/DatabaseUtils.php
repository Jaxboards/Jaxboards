<?php

declare(strict_types=1);

namespace Jax;

use Jax\DatabaseUtils\DatabaseAdapter;
use Jax\DatabaseUtils\MySQL;
use Jax\DatabaseUtils\SQLite;
use Jax\Models\Category;
use Jax\Models\Forum;
use Jax\Models\Group;
use Jax\Models\Post;
use Jax\Models\Skin;
use Jax\Models\Stats;
use Jax\Models\Topic;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function array_map;
use function implode;
use function is_string;
use function mb_check_encoding;
use function mb_convert_encoding;
use function mb_strlen;
use function mb_substr;
use function str_replace;

use const DIRECTORY_SEPARATOR;

final readonly class DatabaseUtils implements DatabaseAdapter
{
    public const ADAPTERS = [
        'mysql' => MySQL::class,
        'sqliteMemory' => SQLite::class,
    ];

    private DatabaseAdapter $databaseAdapter;

    public function __construct(
        private Database $database,
        private FileSystem $fileSystem,
    ) {
        $adapterClass = self::ADAPTERS[$database->driver];
        $this->databaseAdapter = new $adapterClass($database);
    }

    /**
     * Discover model classes under the `Jax\\Models` directory.
     *
     * Returns fully-qualified class names like `Jax\\Models\\Post`.
     */
    public function getModels(): array
    {
        static $modelClassesCache = null;
        if ($modelClassesCache !== null) {
            return $modelClassesCache;
        }

        $modelsDir = __DIR__ . '/Models';
        if (!$this->fileSystem->getFileInfo($modelsDir)->isDir()) {
            return $modelClassesCache = [];
        }

        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modelsDir));
        $modelClassesCache = [];

        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }

            $realPath = $file->getRealPath();
            if ($realPath === false) {
                continue;
            }

            $relative = mb_substr((string) $realPath, mb_strlen($modelsDir) + 1);
            $relative = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
            $relative = mb_substr($relative, 0, -4);
            // strip .php
            $modelClassesCache[] = __NAMESPACE__ . '\Models\\' . $relative;
        }

        return $modelClassesCache;
    }

    public function install(): void
    {
        $this->databaseAdapter->install();

        foreach ($this->getModels() as $modelClass) {
            $model = new $modelClass();
            $queries[] = 'DROP TABLE IF EXISTS ' . $this->database->ftable($model::TABLE);
            $queries[] = $this->databaseAdapter->createTableQueryFromModel($model);
        }

        // Create tables
        foreach ($queries as $query) {
            $this->database->query($query);
        }

        $this->insertInitialRecords();
    }

    public function createTableQueryFromModel(Model $model): string
    {
        return $this->databaseAdapter->createTableQueryFromModel($model);
    }

    /**
     * Build an INSERT SQL query for the given table and data.
     *
     * @param array<array<mixed>> $tableData - an array of rows, each row being an associative array of column => value pairs
     */
    public function buildInsertQuery(
        string $tableName,
        array $tableData,
    ): string {
        $columnNames = [];
        $rows = [[]];

        foreach ($tableData as $rowIndex => $row) {
            foreach ($row as $columnName => $value) {
                if (
                    is_string($value)
                    && !mb_check_encoding($value, 'UTF-8')
                ) {
                    $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                }

                if ($rowIndex === 0) {
                    $columnNames[] = "`{$columnName}`";
                }

                $rows[$rowIndex][] = $this->database->evalue($value);
            }
        }

        $values = implode(', ', array_map(
            static fn($strRow): string => "({$strRow})",
            array_map(static fn(array $row): string => implode(', ', $row), $rows),
        ));

        return "INSERT INTO `{$tableName}`"
            . ' (' . implode(', ', $columnNames) . ')'
            . " VALUES {$values};";
    }

    private function insertInitialRecords(): void
    {
        $category = new Category();
        $category->id = 1;
        $category->title = 'Category';
        $category->insert();

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
        $forum->insert();

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
        $member->canViewFullProfile = 1;
        $member->canPoll = 1;
        $member->insert();

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
        $admin->canViewFullProfile = 1;
        $admin->canPoll = 1;
        $admin->insert();

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
        $guest->canViewFullProfile = 1;
        $guest->insert();

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
        $banned->insert();

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
        $validating->canViewFullProfile = 1;
        $validating->insert();

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
        $post->date = $this->database->datetime();
        $post->insert();

        $skin = new Skin();
        $skin->id = 1;
        $skin->using = 1;
        $skin->title = 'Default';
        $skin->custom = 0;
        $skin->wrapper = 'Default';
        $skin->default = 1;
        $skin->insert();

        $stats = new Stats();
        $stats->id = 1;
        $stats->posts = 1;
        $stats->topics = 1;
        $stats->members = 1;
        $stats->most_members = 1;
        $stats->most_members_day = 1;
        $stats->last_register = 1;
        $stats->dbVersion = 4;
        $stats->insert();

        $topic = new Topic();
        $topic->id = 1;
        $topic->title = 'Welcome to Jaxboards!';
        $topic->subtitle = 'Your support is appreciated.';
        $topic->lastPostUser = 1;
        $topic->lastPostDate = $this->database->datetime();
        $topic->fid = 1;
        $topic->author = 1;
        $topic->summary = " Now, it's only a matter of time";
        $topic->op = 1;
        $topic->insert();
    }
}
