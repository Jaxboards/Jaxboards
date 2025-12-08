<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\API;
use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\BBCode;
use Jax\Config;
use Jax\Database;
use Jax\DatabaseUtils;
use Jax\DatabaseUtils\SQLite;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Model;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\ServiceConfig;
use Jax\TextFormatting;
use Jax\TextRules;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\FeatureTestCase;

use function json_decode;

/**
 * @internal
 */
#[CoversClass(API::class)]
#[CoversClass(Column::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(Key::class)]
#[CoversClass(BBCode::class)]
#[CoversClass(Config::class)]
#[CoversClass(Database::class)]
#[CoversClass(DatabaseUtils::class)]
#[CoversClass(SQLite::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Model::class)]
#[CoversClass(TextRules::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(ServiceConfig::class)]
#[CoversClass(TextFormatting::class)]
#[CoversClass(User::class)]
final class APITest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testSearchMembers(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=searchmembers&term=admin', API::class);

        $this->assertEquals([[1], ['Admin']], json_decode($page, true));
    }

    public function testEmotes(): void
    {
        $this->actingAs('admin');

        $page = $this->go('?act=emotes', API::class);

        $this->assertContains(':)', json_decode($page, true)[0]);
    }
}
