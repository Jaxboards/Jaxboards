#!/usr/bin/env php
<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong,PSR12.Files.FileHeader.IncorrectOrder,Squiz.Commenting.InlineComment.DocBlock,Squiz.Commenting.BlockComment.WrongStart

/**
 * Fetch the greater version property between two json files.
 *
 * USAGE:
 * ```sh
 * <script.php> <first.json> <second.json>
 * ```
 */

// phpcs:enable

$version1 = json_decode(
    file_get_contents($argv[1] ?? ''),
    null,
    // Default
    512,
    JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
)['version'] ?? '0';

$version2 = json_decode(
    file_get_contents($argv[2] ?? ''),
    null,
    // Default
    512,
    JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
)['version'] ?? '0';

if (version_compare($version1, $version2, '>=')) {
    echo $version1;

    exit(0);
}

echo $version2;
