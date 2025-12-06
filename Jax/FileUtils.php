<?php

declare(strict_types=1);

namespace Jax;

use SplFileInfo;
use SplFileObject;

use function array_reverse;
use function closedir;
use function copy;
use function count;
use function file;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function implode;
use function mb_substr;
use function mkdir;
use function opendir;
use function preg_replace;
use function readdir;
use function rmdir;
use function round;
use function trim;
use function unlink;

use const FILE_IGNORE_NEW_LINES;
use const SEEK_END;

/**
 * This class should be used for all file operations (to keep test mocking easy).
 */
final class FileUtils
{
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

            if ($this->isDir($sourcePath)) {
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
        return file_get_contents($filename);
    }

    public function getRealPath(string $filename): string
    {
        $fileInfo = new SplFileInfo($filename);

        return $fileInfo->getRealPath();
    }

    /**
     * Returns an array of lines in a file.
     *
     * @param mixed $flags
     */
    public function getLines(
        string $filename,
        $flags = FILE_IGNORE_NEW_LINES,
    ): array {
        return file($filename, $flags);
    }

    public function glob(string $pattern, int $flags = 0)
    {
        return glob($pattern, $flags);
    }

    public function isDir(string $filename): bool
    {
        $fileInfo = new SplFileInfo($filename);

        return $fileInfo->isDir();
    }

    public function isFile(string $filename): bool
    {
        $fileInfo = new SplFileInfo($filename);

        return $fileInfo->isFile();
    }

    /**
     * Is a file readable?
     */
    public function isReadable(string $filename): bool
    {
        $fileInfo = new SplFileInfo($filename);

        return $fileInfo->isReadable();
    }

    /**
     * Is a file writable?
     */
    public function isWritable(string $filename): bool
    {
        $fileInfo = new SplFileInfo($filename);

        return $fileInfo->isWritable();
    }

    /**
     * Equivalent to Node's path.join method.
     */
    public function pathjoin(string ...$paths): string
    {
        return (string) preg_replace('@\/+@', '/', implode('/', $paths));
    }

    /**
     * Write data to file.
     */
    public function putContents(string $filename, mixed $data): int|false
    {
        return file_put_contents($filename, $data);
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
            if ($this->isDir($fileOrDir)) {
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
        $logFile = new SplFileObject($path, 'r');
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
        return unlink($filename);
    }
}
