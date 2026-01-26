<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\Attributes\Column;
use Jax\Attributes\ForeignKey;
use Jax\Attributes\Key;
use Jax\Config;
use Jax\Database\Adapters\SQLite;
use Jax\Database\Database;
use Jax\Database\Model;
use Jax\Database\Utils as DatabaseUtils;
use Jax\DebugLog;
use Jax\DomainDefinitions;
use Jax\FileSystem;
use Jax\IPAddress;
use Jax\Models\Member;
use Jax\Models\Post;
use Jax\Models\Service\Directory;
use Jax\Request;
use Jax\RequestStringGetter;
use Jax\Routes\ServiceInstall;
use Jax\ServiceConfig;
use Jax\Template;
use Override;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\DOMAssert;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
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
#[CoversClass(Template::class)]
#[AllowMockObjectsWithoutExpectations]
final class ServiceInstallTest extends TestCase
{
    private MockObject&FileSystem $fileSystemMock;

    /**
     * @var array<SplFileInfo>
     */
    private array $mockedFiles = [];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $originalFileSystem = $this->container->get(FileSystem::class);
        $this->fileSystemMock = $this->createMock(FileSystem::class);
        $fileSystemMock = $this->fileSystemMock;

        $allowList = ['glob', 'pathJoin', 'pathFromRoot'];
        foreach ($allowList as $method) {
            $fileSystemMock->method($method)
                ->willReturnCallback($originalFileSystem->{$method}(...))
            ;
        }

        $this->mockedFiles = [];
        $fileSystemMock->method('getFileInfo')
            ->willReturnCallback(
                function (string $filename) use ($originalFileSystem): SplFileInfo {
                    if (array_key_exists($filename, $this->mockedFiles)) {
                        return $this->mockedFiles[$filename];
                    }

                    // Pass through all others
                    return $originalFileSystem->getFileInfo($filename);
                },
            )
        ;

        // Stub out FileSystem
        $this->container->set(FileSystem::class, $fileSystemMock);
    }

    public function testInstallerFormInstalled(): void
    {
        $this->mockedFiles['config.php'] = self::createConfiguredStub(
            SplFileInfo::class,
            ['isFile' => true],
        );

        $page = $this->goServiceInstall();

        self::assertStringContainsString(
            'Detected config.php at root.',
            $page,
        );
    }

    public function testInstallerFormNotInstalled(): void
    {
        $this->mockedFiles['config.php'] = self::createConfiguredStub(
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
        $this->mockedFiles['config.php'] = self::createConfiguredStub(
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
        self::assertEquals(false, $serviceConfig['service']);
        self::assertEquals('Jaxboards', $serviceConfig['boardname']);
        self::assertEquals('domain.com', $serviceConfig['domain']);
        self::assertEquals(
            'Sean <admin_email@jaxboards.com>',
            $serviceConfig['mail_from'],
        );
        self::assertEquals('jaxboards', $serviceConfig['prefix']);
        self::assertEquals('sql_db', $serviceConfig['sql_db']);
        self::assertEquals('sql_host', $serviceConfig['sql_host']);
        self::assertEquals('sql_username', $serviceConfig['sql_username']);
        self::assertEquals('sql_password', $serviceConfig['sql_password']);
        self::assertEquals('jaxboards_', $serviceConfig['sql_prefix']);

        // Do some spot checking to see if the installer
        // set up the tables based on form data
        self::assertEquals(1, Post::selectOne(1)->author);

        $member = Member::selectOne(1);
        self::assertEquals('Sean', $member->displayName);
        self::assertEquals('admin_email@jaxboards.com', $member->email);
        self::assertTrue(password_verify('password', $member->pass));

        self::assertStringContainsString('Redirecting', $page);
    }

    public function testInstallerFormSubmitServiceMode(): void
    {
        $this->mockedFiles['config.php'] = self::createConfiguredStub(
            SplFileInfo::class,
            ['isFile' => false],
        );

        // Assert that the boards directory is set up
        $this->fileSystemMock->expects($this->exactly(2))
            ->method('copyDirectory')
            ->with(
                'Service/blueprint',
                self::callback(
                    static fn($path): bool => in_array($path, [
                        'boards/test',
                        'boards/support',
                    ], true),
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
        self::assertEquals(true, $serviceConfig['service']);
        self::assertEquals('Jaxboards', $serviceConfig['boardname']);
        self::assertEquals('domain.com', $serviceConfig['domain']);
        self::assertEquals(
            'Sean <admin_email@jaxboards.com>',
            $serviceConfig['mail_from'],
        );
        self::assertEquals('', $serviceConfig['prefix']);
        self::assertEquals('sql_db', $serviceConfig['sql_db']);
        self::assertEquals('sql_host', $serviceConfig['sql_host']);
        self::assertEquals('sql_username', $serviceConfig['sql_username']);
        self::assertEquals('sql_password', $serviceConfig['sql_password']);
        self::assertEquals('', $serviceConfig['sql_prefix']);

        // Do some spot checking to see if the installer
        // set up the tables based on form data
        self::assertEquals(1, Post::selectOne(1)->author);

        $member = Member::selectOne(1);
        self::assertEquals('Sean', $member->displayName);
        self::assertEquals('admin_email@jaxboards.com', $member->email);
        self::assertTrue(password_verify('password', $member->pass));

        $this->container->get(Database::class)->setPrefix('');
        $directory = Directory::selectOne(1);
        self::assertEquals(
            'admin_email@jaxboards.com',
            $directory->registrarEmail,
        );
        self::assertEquals('support', $directory->boardname);

        self::assertStringContainsString('Redirecting', $page);
    }

    private function goServiceInstall(?Request $request = null): string
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
