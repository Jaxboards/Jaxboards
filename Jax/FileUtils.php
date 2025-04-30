<?php

declare(strict_types=1);

namespace Jax;

use function closedir;
use function copy;
use function glob;
use function is_dir;
use function mb_substr;
use function mkdir;
use function opendir;
use function readdir;
use function round;
use function unlink;
use function str_replace;

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
    public static function copyDirectory($src, $dst): bool
    {
        $dir = opendir($src);

        if (!$dir || !mkdir($dst)) {
            return false;
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
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
    public static function removeDirectory(string $dir): bool
    {
        if (mb_substr($dir, -1) !== '/') {
            $dir .= '/';
        }

        foreach (glob($dir . '*') as $v) {
            if (is_dir($v)) {
                self::removeDirectory($v);

                continue;
            }

            unlink($v);
        }

        self::removeDirectory($dir);

        return true;
    }

    /**
     * Computes a human readable filesize.
     */
    public static function fileSizeHumanReadable(int $sizeInBytes): string
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
     * Given a file path, returns the corresponding class path
     * @return class-string
     */
    public static function toClassPath(string $file): string
    {
        return str_replace([dirname(__DIR__), '.php', '/'], ['', '', '\\'], $file);
    }
}
