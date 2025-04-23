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

$input1 = file_get_contents($argv[1] ?? '');
$version1 = '0';
if ($input1 !== false) {
    $version1 = json_decode(
        $input1,
        null,
        // Default
        512,
        JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
    )['version'] ?? '0';
}

$input2 = file_get_contents($argv[2] ?? '');
$version2 = '0';
if ($input2 !== false) {
    $version2 = json_decode(
        $input2,
        null,
        // Default
        512,
        JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
    )['version'] ?? '0';
}

if (version_compare($version1, $version2, '>=')) {
    echo $version1;

    exit(0);
}

echo $version2;
