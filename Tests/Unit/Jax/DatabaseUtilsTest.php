<?php

declare(strict_types=1);

namespace Tests\Unit\Jax;

use Jax\DatabaseUtils\SQLite;
use Jax\Database;
use Jax\DatabaseUtils;
use Jax\Model;
use Jax\ServiceConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use Tests\UnitTestCase;

/**
 * @internal
 */
#[CoversClass(DatabaseUtils::class)]
#[CoversClass(Database::class)]
#[CoversClass(SQLite::class)]
#[CoversClass(Model::class)]
#[CoversClass(ServiceConfig::class)]
#[Small]
final class DatabaseUtilsTest extends UnitTestCase
{
    private DatabaseUtils $databaseUtils;

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

        $this->assertEquals(
            "INSERT INTO `test_table` (`id`, `name`, `age`) VALUES (1, 'O''Reilly', 30), (2, 'Alice', 25);",
            $query
        );
    }
}
