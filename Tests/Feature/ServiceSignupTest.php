<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Config;
use Jax\Database;
use Jax\DatabaseUtils;
use Jax\DatabaseUtils\SQLite;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\IPAddress;
use Jax\Model;
use Jax\Page\ServiceSignup;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\ServiceConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;
use function DI\autowire;

/**
 * The installer test is special.
 *
 * It should run standalone and not extend a normal feature test.
 *
 * It fully mocks out the filesystem and assumes the database has not yet been installed.
 *
 * @internal
 */
#[CoversClass(ServiceSignup::class)]
#[CoversClass(Column::class)]
#[CoversClass(ForeignKey::class)]
#[CoversClass(Key::class)]
#[CoversClass(Config::class)]
#[CoversClass(Database::class)]
#[CoversClass(DatabaseUtils::class)]
#[CoversClass(SQLite::class)]
#[CoversClass(DebugLog::class)]
#[CoversClass(DomainDefinitions::class)]
#[CoversClass(FileSystem::class)]
#[CoversClass(IPAddress::class)]
#[CoversClass(Model::class)]
#[CoversClass(Request::class)]
#[CoversClass(RequestStringGetter::class)]
#[CoversClass(ServiceConfig::class)]
final class ServiceSignupTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }


    public function testSignupFormServiceModeDisabled(): void
    {
        $this->setServiceConfig([ 'service' => false ]);

        $page = $this->go(
            new Request(
                server: ['SERVER_NAME' => 'www.jaxboards.com'],
            ),
            pageClass: ServiceSignup::class
        );

        $this->assertEquals('Service mode not enabled', $page);
    }

    public function testSignupFormServiceModeEnabled(): void
    {
        $this->setServiceConfig([ 'service' => true ]);

        $page = $this->go(
            new Request(
                server: ['SERVER_NAME' => 'www.jaxboards.com'],
            ),
            pageClass: ServiceSignup::class
        );

        DOMAssert::assertSelectCount('input[name=boardurl]', 1, $page);
        DOMAssert::assertSelectCount('input[name=username]', 1, $page);
        DOMAssert::assertSelectCount('input[name=password]', 1, $page);
        DOMAssert::assertSelectCount('input[name=username]', 1, $page);
        DOMAssert::assertSelectCount('input[name=email]', 1, $page);
        DOMAssert::assertSelectCount('input[name=post]', 1, $page);
    }
}
