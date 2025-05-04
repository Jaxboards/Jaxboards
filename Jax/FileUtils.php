<?php

declare(strict_types=1);

namespace Jax;

use SplFileObject;

use function array_reverse;
use function closedir;
use function copy;
use function count;
use function dirname;
use function glob;
use function is_dir;
use function mb_substr;
use function mkdir;
use function opendir;
use function readdir;
use function round;
use function str_replace;
use function trim;
use function unlink;

use const SEEK_END;

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

            if (is_dir($sourcePath)) {
                self::copyDirectory($sourcePath, $destPath);

                continue;
            }

            copy($sourcePath, $destPath);
        }

        closedir($dir);

        return true;
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

        foreach (glob($dir . '**') as $fileOrDir) {
            var_dump($fileOrDir);
            if (is_dir($fileOrDir)) {
                self::removeDirectory($fileOrDir);

                continue;
            }

            unlink($fileOrDir);
        }

        rmdir($dir);

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

    /**
     * Reads the last $totalLines of a file.
     *
     * @return array<string>
     */
    public function tail(bool|string $path, int $totalLines): array
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

    /**
     * Given a file path, returns the corresponding class path.
     *
     * @return class-string
     */
    public function toClassPath(string $file): string
    {
        return str_replace([dirname(__DIR__), '.php', '/'], ['', '', '\\'], $file);
    }
}
