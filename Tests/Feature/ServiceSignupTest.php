<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\BotDetector;
use Jax\Config;
use Jax\Database\Adapters\SQLite;
use Jax\Database\Database;
use Jax\Database\Model;
use Jax\Database\Utils as DatabaseUtils;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\IPAddress;
use Jax\Jax;
use Jax\Models\Service\Directory;
use Jax\Page;
use Jax\Page\ServiceSignup;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Router;
use Jax\ServiceConfig;
use Jax\Session;
use Jax\Template;
use Jax\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use Tests\FeatureTestCase;

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
#[CoversClass(BotDetector::class)]
#[CoversClass(Jax::class)]
#[CoversClass(Page::class)]
#[CoversClass(Router::class)]
#[CoversClass(Session::class)]
#[CoversClass(Template::class)]
#[CoversClass(User::class)]
final class ServiceSignupTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testSignupFormServiceModeDisabled(): void
    {
        $this->setServiceConfig(['service' => false]);

        $page = $this->go(
            new Request(
                server: ['SERVER_NAME' => 'www.jaxboards.com'],
            ),
            pageClass: ServiceSignup::class,
        );

        $this->assertEquals('Service mode not enabled', $page);
    }

    public function testSignupFormServiceModeEnabled(): void
    {
        $this->setServiceConfig(['service' => true]);

        $page = $this->go(
            new Request(
                server: ['SERVER_NAME' => 'www.jaxboards.com'],
            ),
            pageClass: ServiceSignup::class,
        );

        DOMAssert::assertSelectCount('input[name=boardurl]', 1, $page);
        DOMAssert::assertSelectCount('input[name=username]', 1, $page);
        DOMAssert::assertSelectCount('input[name=password]', 1, $page);
        DOMAssert::assertSelectCount('input[name=username]', 1, $page);
        DOMAssert::assertSelectCount('input[name=email]', 1, $page);
        DOMAssert::assertSelectCount('input[name=post]', 1, $page);
    }

    public function testSignupFormServiceModeEnabledSubmit(): void
    {
        $this->setServiceConfig(['service' => true]);

        $this->container->get(DatabaseUtils::class)->installServiceTables();

        // Assert that the boards directory is set up
        $fileSystem = $this->container->get(FileSystem::class);
        $fileSystem->expects($this->once())
            ->method('copyDirectory')
            ->with('Service/blueprint', 'boards/boardname')
        ;

        $page = $this->go(
            new Request(
                post: [
                    'username' => 'username',
                    'password' => 'password',
                    'boardurl' => 'boardname',
                    'email' => 'email@email.com',
                    'submit' => 'Register a Forum!',
                ],
                server: [
                    'SERVER_NAME' => 'www.jaxboards.com',
                    'REMOTE_ADDR' => '::1',
                ],
            ),
            pageClass: ServiceSignup::class,
        );

        $this->assertRedirect('https://boardname.jaxboards.com', [], $page);

        // Check board directory was added
        $database = $this->container->get(Database::class);
        $database->setPrefix('');

        $directoryEntry = Directory::selectOne(1);
        $this->assertEquals('email@email.com', $directoryEntry->registrarEmail);
        $this->assertEquals('boardname', $directoryEntry->boardname);
    }
}
