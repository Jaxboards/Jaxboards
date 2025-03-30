#!/usr/bin/env php
<?php

/**
 * Fetch the composer version for use in our pre-commit hook
 */

define('PACKAGE_FILE', dirname(__DIR__) . '/package.json');

$package_json = file_get_contents(PACKAGE_FILE);

$package_data = json_decode(
    $package_json,
    null,
    // Default
    512,
    JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
);

echo $package_data['engines']['composer'] ?? null;
