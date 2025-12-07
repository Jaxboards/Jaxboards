<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jax\FileSystem;
use Jax\Page\ServiceInstall;
use Jax\Request;
use PHPUnit\Framework\DOMAssert;
use SplFileInfo;
use Tests\FeatureTestCase;

use function array_key_exists;
use function DI\autowire;

/**
 * @internal
 *
 * @coversNothing
 */
final class ServiceInstallTest extends FeatureTestCase
{
    /**
     * @var array<SplFileInfo>
     */
    private array $mockedFiles = [];

    protected function setUp(): void
    {
        $originalFileSystem = $this->container->get(FileSystem::class);

        $fileSystemStub = $this->createStub(FileSystem::class);

        // Pass through necessary reads
        $fileSystemStub->method('glob')
            ->willReturnCallback($originalFileSystem->glob(...))
        ;

        $this->mockedFiles = [];
        $fileSystemStub->method('getFileInfo')
            ->willReturnCallback(function (string $filename) use ($originalFileSystem): SplFileInfo {
                if (array_key_exists($filename, $this->mockedFiles)) {
                    return $this->mockedFiles[$filename];
                }

                // Pass through all others
                return $originalFileSystem->getFileInfo($filename);
            })
        ;

        // Stub out FileSystem
        $this->container->set(FileSystem::class, $fileSystemStub);

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
        DOMAssert::assertSelectCount('input[name=domain]', 1, $page);
        DOMAssert::assertSelectCount('input[name=sql_db]', 1, $page);
        DOMAssert::assertSelectCount('input[name=sql_host]', 1, $page);
        DOMAssert::assertSelectCount('input[name=sql_username]', 1, $page);
        DOMAssert::assertSelectCount('input[name=sql_password]', 1, $page);
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
