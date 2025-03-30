#!/usr/bin/env php
<?php

/**
 * Fetch the composer version for use in our pre-commit hook.
 */
define('PACKAGE_FILE', dirname(__DIR__) . '/package.json');

$packageJSON = file_get_contents(PACKAGE_FILE);

$packageData = json_decode(
    $packageJSON,
    null,
    // Default
    512,
    JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
);

echo $packageData['engines']['composer'] ?? null;
