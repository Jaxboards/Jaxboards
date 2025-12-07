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
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Models\Service\Directory;
use Jax\Page\ServiceInstall;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\ServiceConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use PHPUnit\Framework\MockObject\MockObject;
use SplFileInfo;
use Tests\TestCase;

use function array_key_exists;
use function DI\autowire;
use function in_array;
use function password_verify;

/**
 * The installer test is special.
 *
 * It should run standalone and not extend a normal feature test.
 *
 * It fully mocks out the filesystem and assumes the database has not yet been installed.
 *
 * @internal
 */
#[CoversClass(ServiceInstall::class)]
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
final class ServiceInstallTest extends TestCase
{
    private MockObject&FileSystem $fileSystemMock;

    /**
     * @var array<SplFileInfo>
     */
    private array $mockedFiles = [];

    protected function setUp(): void
    {
        $originalFileSystem = $this->container->get(FileSystem::class);
        $this->fileSystemMock = $this->createMock(FileSystem::class);
        $fileSystemMock = $this->fileSystemMock;

        // Pass through the models glob
        $fileSystemMock->method('glob')
            ->willReturnCallback($originalFileSystem->glob(...))
        ;

        $this->mockedFiles = [];
        $fileSystemMock->method('getFileInfo')
            ->willReturnCallback(function (string $filename) use ($originalFileSystem): SplFileInfo {
                if (array_key_exists($filename, $this->mockedFiles)) {
                    return $this->mockedFiles[$filename];
                }

                // Pass through all others
                return $originalFileSystem->getFileInfo($filename);
            })
        ;

        // Stub out FileSystem
        $this->container->set(FileSystem::class, $fileSystemMock);

        parent::setUp();
    }

    public function testInstallerFormInstalled(): void
    {
        $this->mockedFiles['config.php'] = $this->createConfiguredStub(
            SplFileInfo::class,
            ['isFile' => true],
        );

        $page = $this->goServiceInstall();

        $this->assertStringContainsString('Detected config.php at root.', $page);
    }

    public function testInstallerFormNotInstalled(): void
    {
        $this->mockedFiles['config.php'] = $this->createConfiguredStub(
            SplFileInfo::class,
            ['isFile' => false],
        );

        $page = $this->goServiceInstall();

        DOMAssert::assertSelectCount('input[name=service]', 1, $page);
        DOMAssert::assertSelectCount('input[name=admin_username]', 1, $page);
        DOMAssert::assertSelectCount('input[name=admin_password]', 1, $page);
        DOMAssert::assertSelectCount('input[name=admin_password_2]', 1, $page);
        DOMAssert::assertSelectCount('input[name=admin_email]', 1, $page);
        DOMAssert::assertSelectCount('input[name=domain]', 1, $page);
        DOMAssert::assertSelectCount('input[name=sql_db]', 1, $page);
        DOMAssert::assertSelectCount('input[name=sql_host]', 1, $page);
        DOMAssert::assertSelectCount('input[name=sql_username]', 1, $page);
        DOMAssert::assertSelectCount('input[name=sql_password]', 1, $page);
    }

    public function testInstallerFormSubmitNormalMode(): void
    {
        $this->mockedFiles['config.php'] = $this->createConfiguredStub(
            SplFileInfo::class,
            ['isFile' => false],
        );

        // Assert that the boards directory is set up
        $this->fileSystemMock->expects($this->once())
            ->method('copyDirectory')
            ->with('Service/blueprint', 'boards/jaxboards')
        ;

        $page = $this->goServiceInstall(new Request(
            post: [
                // 'service' =>
                'admin_username' => 'Sean',
                'admin_password' => 'password',
                'admin_password_2' => 'password',
                'admin_email' => 'admin_email@jaxboards.com',
                'domain' => 'domain.com',
                'sql_db' => 'sql_db',
                'sql_host' => 'sql_host',
                'sql_username' => 'sql_username',
                'sql_password' => 'sql_password',
                'sql_driver' => 'sqliteMemory',
                'submit' => 'Start your service!',
            ],
        ));

        // Assert the config was written
        $serviceConfig = $this->container->get(ServiceConfig::class)->get();
        $this->assertEquals(false, $serviceConfig['service']);
        $this->assertEquals('Jaxboards', $serviceConfig['boardname']);
        $this->assertEquals('domain.com', $serviceConfig['domain']);
        $this->assertEquals('Sean <admin_email@jaxboards.com>', $serviceConfig['mail_from']);
        $this->assertEquals('jaxboards', $serviceConfig['prefix']);
        $this->assertEquals('sql_db', $serviceConfig['sql_db']);
        $this->assertEquals('sql_host', $serviceConfig['sql_host']);
        $this->assertEquals('sql_username', $serviceConfig['sql_username']);
        $this->assertEquals('sql_password', $serviceConfig['sql_password']);
        $this->assertEquals('jaxboards_', $serviceConfig['sql_prefix']);

        // Do some spot checking to see if the installer
        // set up the tables based on form data
        $this->assertEquals(Post::selectOne(1)->author, 1);

        $member = Member::selectOne(1);
        $this->assertEquals($member->displayName, 'Sean');
        $this->assertEquals($member->email, 'admin_email@jaxboards.com');
        $this->assertTrue(password_verify('password', $member->pass));

        $this->assertStringContainsString('Redirecting', $page);
    }

    public function testInstallerFormSubmitServiceMode(): void
    {
        $this->mockedFiles['config.php'] = $this->createConfiguredStub(
            SplFileInfo::class,
            ['isFile' => false],
        );

        // Assert that the boards directory is set up
        $this->fileSystemMock->expects($this->exactly(2))
            ->method('copyDirectory')
            ->with(
                'Service/blueprint',
                $this->callback(
                    static fn($path): bool => in_array($path, [
                        'boards/test',
                        'boards/support',
                    ]),
                ),
            )
        ;


        $page = $this->goServiceInstall(new Request(
            post: [
                'service' => 'on',
                'admin_username' => 'Sean',
                'admin_password' => 'password',
                'admin_password_2' => 'password',
                'admin_email' => 'admin_email@jaxboards.com',
                'domain' => 'domain.com',
                'sql_db' => 'sql_db',
                'sql_host' => 'sql_host',
                'sql_username' => 'sql_username',
                'sql_password' => 'sql_password',
                'sql_driver' => 'sqliteMemory',
                'submit' => 'Start your service!',
            ],
        ));

        // Assert the config was written
        $serviceConfig = $this->container->get(ServiceConfig::class)->get();
        $this->assertEquals(true, $serviceConfig['service']);
        $this->assertEquals('Jaxboards', $serviceConfig['boardname']);
        $this->assertEquals('domain.com', $serviceConfig['domain']);
        $this->assertEquals('Sean <admin_email@jaxboards.com>', $serviceConfig['mail_from']);
        $this->assertEquals('', $serviceConfig['prefix']);
        $this->assertEquals('sql_db', $serviceConfig['sql_db']);
        $this->assertEquals('sql_host', $serviceConfig['sql_host']);
        $this->assertEquals('sql_username', $serviceConfig['sql_username']);
        $this->assertEquals('sql_password', $serviceConfig['sql_password']);
        $this->assertEquals('', $serviceConfig['sql_prefix']);

        // Do some spot checking to see if the installer
        // set up the tables based on form data
        $this->assertEquals(Post::selectOne(1)->author, 1);

        $member = Member::selectOne(1);
        $this->assertEquals('Sean', $member->displayName);
        $this->assertEquals('admin_email@jaxboards.com', $member->email);
        $this->assertTrue(password_verify('password', $member->pass));

        $this->container->get(Database::class)->setPrefix('');
        $directory = Directory::selectOne(1);
        $this->assertEquals('admin_email@jaxboards.com', $directory->registrarEmail);
        $this->assertEquals('support', $directory->boardname);

        $this->assertStringContainsString('Redirecting', $page);
    }

    private function goServiceInstall(?Request $request = null)
    {
        if ($request instanceof Request) {
            $this->container->set(
                ServiceInstall::class,
                autowire()->constructorParameter('request', $request),
            );
        }

        return $this->container->get(ServiceInstall::class)->render();
    }
}
