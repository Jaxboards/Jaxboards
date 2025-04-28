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
use function strlen;
use function unlink;

final class FileUtils
{
    /**
     * Recursively copies one directory to another.
     *
     * @param string $src The source directory- this must exist already
     * @param string $dst The destination directory- this is assumed to not exist already
     */
    public static function copyDirectory($src, $dst): void
    {
        $dir = opendir($src);
        mkdir($dst);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.') {
                continue;
            }
            if ($file === '..') {
                continue;
            }
            if (is_dir($src . '/' . $file)) {
                self::copyDirectory($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
        closedir($dir);
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
            } else {
                unlink($v);
            }
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
        while ($sizeInBytes > 1024) {
            $sizeInBytes /= 1024;
            ++$magnitude;
        }

        $prefix = $magnitude > 0 && $magnitude < strlen($sizes)
            ? $sizes[$magnitude]
            : '';

        return round($sizeInBytes, 2) . "{$prefix}B";
    }
}
