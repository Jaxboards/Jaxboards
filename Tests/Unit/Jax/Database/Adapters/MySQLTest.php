<?php

declare(strict_types=1);

namespace Tests\Unit\Jax\Database\Adapters;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Database\Adapters\MySQL;
use Jax\Database\Database;
use Jax\Database\Model;
use Jax\FileSystem;
use Jax\Models\Member;
use Jax\ServiceConfig;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use Spatie\Snapshots\MatchesSnapshots;
use Tests\UnitTestCase;

/**
 * @internal
 */
#[CoversClass(MySQL::class)]
#[CoversClass(Column::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(Key::class)]
#[CoversClass(Database::class)]
#[CoversClass(Model::class)]
#[CoversClass(ServiceConfig::class)]
#[Small]
final class MySQLTest extends UnitTestCase
{
    use MatchesSnapshots;

    private MySQL $mySQL;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->mySQL = $this->container->get(MySQL::class);
    }

    public function testCreateTableQueryFromModel(): void
    {
        $createTable = $this->mySQL->createTableQueryFromModel(new Member());

        $this->assertMatchesSnapshot($createTable);
    }
}
