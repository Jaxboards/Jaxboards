<?php

declare(strict_types=1);

namespace Tests\Unit\Jax\Database;

use Jax\Database\Adapters\SQLite;
use Jax\Database\Database;
use Jax\Database\Model;
use Jax\Database\Utils as DatabaseUtils;
use Jax\FileSystem;
use Jax\ServiceConfig;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Small;
use Tests\UnitTestCase;

/**
 * @internal
 */
#[CoversClass(DatabaseUtils::class)]
#[CoversClass(Database::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(SQLite::class)]
#[CoversClass(Model::class)]
#[CoversClass(ServiceConfig::class)]
#[Small]
final class UtilsTest extends UnitTestCase
{
    private DatabaseUtils $databaseUtils;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseUtils = $this->container->get(DatabaseUtils::class);
    }

    public function testBuildInsertQuery(): void
    {
        $query = $this->databaseUtils->buildInsertQuery('test_table', [
            ['id' => 1, 'name' => "O'Reilly", 'age' => 30],
            ['id' => 2, 'name' => 'Alice', 'age' => 25],
        ]);

        static::assertSame(
            "INSERT INTO `test_table` (`id`, `name`, `age`) VALUES (1, 'O''Reilly', 30), (2, 'Alice', 25);",
            $query,
        );
    }

    public function testGetMigrations(): void
    {
        $migrations = $this->databaseUtils->getMigrations();
        $versions = array_keys($migrations);
        sort($versions);
        static::assertEqualsCanonicalizing(array_keys($migrations), $versions);
        static::assertGreaterThan(6, array_key_last($migrations));
    }
}
