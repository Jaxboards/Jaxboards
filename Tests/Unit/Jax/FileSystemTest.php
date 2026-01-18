<?php

declare(strict_types=1);

namespace Tests\Unit\Jax;

use Jax\FileSystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use Tests\UnitTestCase;

use function implode;
use function range;
use function sys_get_temp_dir;

/**
 * @internal
 */
#[CoversClass(FileSystem::class)]
#[Small]
final class FileSystemTest extends UnitTestCase
{
    private FileSystem $fileSystem;

    protected function setUp(): void
    {
        $this->fileSystem = new FileSystem(sys_get_temp_dir());
        parent::setUp();
    }

    public function testOperations(): void
    {
        // mkdir
        $this->fileSystem->mkdir('jaxboards/deep', recursive: true);
        $this->assertTrue($this->fileSystem->getFileInfo('jaxboards/deep')->isDir());

        // putContents
        $this->fileSystem->putContents(
            'jaxboards/test',
            implode("\n", range(1, 100)),
        );

        $fileInfo = $this->fileSystem->getFileInfo('jaxboards/test');
        $this->assertTrue($fileInfo->isFile());

        // tail
        $this->assertEquals(
            range(96, 100),
            $this->fileSystem->tail('jaxboards/test', 5),
        );

        // rename
        $this->fileSystem->rename('jaxboards/test', 'jaxboards/renamed');
        $this->assertTrue($this->fileSystem->getFileInfo('jaxboards/renamed')->isFile());

        // Create test file again so we have multiple files
        $this->fileSystem->putContents('jaxboards/test', implode("\n", range(1, 5)));

        // copyDirectory
        $this->fileSystem->copyDirectory('jaxboards', 'jaxboards2');
        $this->assertTrue($this->fileSystem->getFileInfo('jaxboards2/renamed')->isFile());
        $this->assertTrue($this->fileSystem->getFileInfo('jaxboards2/test')->isFile());

        // glob
        $this->assertEqualsCanonicalizing(
            $this->fileSystem->glob('jaxboards/*'),
            ['jaxboards/test', 'jaxboards/renamed', 'jaxboards/deep'],
        );

        // getLines
        $this->assertEquals(
            $this->fileSystem->getLines('jaxboards/test'),
            range(1, 5),
        );

        // Cleanup and assert on the way out
        $this->fileSystem->removeDirectory('jaxboards');
        $this->fileSystem->removeDirectory('jaxboards2');
        $this->assertFalse($this->fileSystem->getFileInfo('jaxboards/test')->isFile());
        $this->assertFalse($this->fileSystem->getFileInfo('jaxboards/renamed')->isFile());
        $this->assertFalse($this->fileSystem->getFileInfo('jaxboards2')->isDir());
    }

    #[DataProvider('fileSizeHumanReadableDataProvider')]
    public function testFileSizeHumanReadable(
        int $fileSize,
        string $readable,
    ): void {
        $this->assertEquals($readable, $this->fileSystem->fileSizeHumanReadable($fileSize));
    }

    /**
     * @return array<array{int, string}>
     */
    public static function fileSizeHumanReadableDataProvider(): array
    {
        return [
            [5, '5B'],
            [1 << 10, '1KB'],
            [3 << 9, '1.5KB'],
            [1 << 20, '1MB'],
            [1 << 30, '1GB'],
            [1 << 40, '1TB'],
            [1 << 50, '1EB'],
        ];
    }
}
