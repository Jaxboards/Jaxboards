#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Fetch the composer version for use in our pre-commit hook.
 */
define('PACKAGE_FILE', dirname(__DIR__) . '/package.json');

$packageJSON = file_get_contents(PACKAGE_FILE);

if ($packageJSON === false) {
    fwrite(STDERR, 'Could not read ' . PACKAGE_FILE);

    exit(1);
}

$packageData = json_decode(
    $packageJSON,
    null,
    // Default
    512,
    JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
);

echo $packageData['engines']['composer'] ?? null;
