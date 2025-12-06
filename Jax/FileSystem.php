<?php

declare(strict_types=1);

namespace Jax;

use SplFileInfo;
use SplFileObject;

use function array_map;
use function array_reverse;
use function closedir;
use function copy;
use function count;
use function dirname;
use function glob;
use function implode;
use function iterator_to_array;
use function mb_strlen;
use function mb_substr;
use function mkdir;
use function opendir;
use function preg_replace;
use function readdir;
use function rmdir;
use function round;
use function trim;
use function unlink;

use const PHP_EOL;
use const SEEK_END;

/**
 * This class should be used for all file operations (to keep test mocking easy).
 */
final readonly class FileSystem
{
    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? dirname(__DIR__);
    }

    /**
     * Recursively copies one directory to another.
     *
     * @param string $src The source directory- this must exist already
     * @param string $dst The destination directory- this is assumed to not exist already
     *
     * @return bool true on success, false on failure
     */
    public function copyDirectory($src, $dst): bool
    {
        $dir = opendir($src);

        if (!$dir || !mkdir($dst)) {
            return false;
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.') {
                continue;
            }

            if ($file === '..') {
                continue;
            }

            $sourcePath = "{$src}/{$file}";
            $destPath = "{$dst}/{$file}";

            if ($this->getFileInfo($sourcePath)->isDir()) {
                self::copyDirectory($sourcePath, $destPath);

                continue;
            }

            copy($sourcePath, $destPath);
        }

        closedir($dir);

        return true;
    }

    /**
     * Computes a human readable filesize.
     */
    public function fileSizeHumanReadable(int $sizeInBytes): string
    {
        $magnitude = 0;
        $sizes = ' KMGTE';
        while ($sizeInBytes > 1_024) {
            $sizeInBytes /= 1_024;
            ++$magnitude;
        }

        $prefix = $magnitude > 0 && $magnitude <= 5
            ? $sizes[$magnitude]
            : '';

        return round($sizeInBytes, 2) . "{$prefix}B";
    }

    public function getContents(string $filename): string|false
    {
        return implode(PHP_EOL, $this->getLines($filename));
    }

    /**
     * Get FileInfo for a file.
     *
     * @param string $filename relative path from root
     */
    public function getFileInfo(string $filename): SplFileInfo
    {
        return new SplFileInfo($this->pathFromRoot($filename));
    }

    public function getFileObject(
        string $filename,
        string $mode = 'r',
    ): SplFileObject {
        return new SplFileObject($this->pathFromRoot($filename), $mode);
    }

    /**
     * Returns an array of lines in a file.
     */
    public function getLines(string $filename): array
    {
        $file = $this->getFileObject($filename);

        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        return iterator_to_array($file);
    }

    /**
     * Returns list of files that match pattern relative to jaxboards root.
     */
    public function glob(string $pattern, int $flags = 0): array
    {
        return array_map(
            fn($path): string => mb_substr($path, mb_strlen($this->root)),
            glob($this->pathFromRoot($pattern), $flags),
        );
    }

    /**
     * Returns the fully qualified path from root of jaxboards.
     */
    public function pathFromRoot(string ...$paths): string
    {
        return $this->pathJoin($this->root, ...$paths);
    }

    /**
     * Equivalent to Node's path.join method.
     */
    public function pathJoin(string ...$paths): string
    {
        return (string) preg_replace('@\/+@', '/', implode('/', $paths));
    }

    /**
     * Write data to file.
     */
    public function putContents(string $filename, mixed $data): int|false
    {
        $file = $this->getFileObject($filename, 'w');

        return $file->fwrite($data);
    }

    /**
     * Recursively removes a whole directory and its files.
     * Equivalent to `rmdir -r`.
     */
    public function removeDirectory(string $dir): bool
    {
        if (mb_substr($dir, -1) !== '/') {
            $dir .= '/';
        }

        foreach ($this->glob($dir . '**') ?: [] as $fileOrDir) {
            if ($this->getFileInfo($fileOrDir)->isDir()) {
                self::removeDirectory($fileOrDir);

                continue;
            }

            unlink($fileOrDir);
        }

        rmdir($dir);

        return true;
    }

    /**
     * Reads the last $totalLines of a file.
     *
     * @return array<string>
     */
    public function tail(string $path, int $totalLines): array
    {
        $logFile = $this->getFileObject($path, 'r');
        $logFile->fseek(0, SEEK_END);

        $lines = [];
        $lastLine = '';

        // Loop backward until we have our lines or we reach the start
        for ($pos = $logFile->ftell() - 1; $pos >= 0; --$pos) {
            $logFile->fseek($pos);
            $character = $logFile->fgetc();

            if ($pos === 0 || $character !== "\n") {
                $lastLine = $character . $lastLine;
            }

            if ($pos !== 0 && $character !== "\n") {
                continue;
            }

            // skip empty lines
            if (trim($lastLine) === '') {
                continue;
            }

            $lines[] = $lastLine;
            $lastLine = '';

            if (count($lines) >= $totalLines) {
                break;
            }
        }

        return array_reverse($lines);
    }

    public function unlink(string $filename): bool
    {
        return unlink($this->pathFromRoot($filename));
    }
}
