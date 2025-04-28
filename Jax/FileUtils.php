<?php

namespace Jax\FileUtils;

/**
 * Recursively copies one directory to another.
 *
 * @param string $src The source directory- this must exist already
 * @param string $dst The destination directory- this is assumed to not exist already
 */
function copyDirectory($src, $dst): void
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
            copyDirectory($src . '/' . $file, $dst . '/' . $file);
        } else {
            copy($src . '/' . $file, $dst . '/' . $file);
        }
    }
    closedir($dir);
};


/**
 * Recursively removes a whole directory and its files.
 * Equivalent to `rmdir -r`
 */
function removeDirectory(string $dir): bool
{
    if (mb_substr($dir, -1) !== '/') {
        $dir .= '/';
    }

    foreach (glob($dir . '*') as $v) {
        if (is_dir($v)) {
            removeDirectory($v);
        } else {
            unlink($v);
        }
    }

    removeDirectory($dir);

    return true;
}

/**
 * Computes a human readable filesize.
 *
 */
function fileSizeHumanReadable(int $sizeInBytes): string
{
    $magnitude = 0;
    $sizes = ' KMGTE';
    while ($sizeInBytes > 1024) {
        $sizeInBytes /= 1024;
        ++$magnitude;
    }

    $prefix = $magnitude > 0 && $magnitude < strlen($sizes) ? $sizes[$magnitude] : '';

    return round($sizeInBytes, 2) . "{$prefix}B";
}
