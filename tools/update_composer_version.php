#!/usr/bin/env php
<?php

/**
 * Update our composer version to the latest available
 */

$version_json = file_get_contents('https://getcomposer.org/versions');

$versions = json_decode(
    $version_json,
    null,
    // Default
    512,
    JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
);

$version = $versions['stable'][0]['version'] ?? null;

if ($version === null) {
    fwrite(STDERR, 'Could not retrieve composer version' . PHP_EOL);

    exit(1);
}

define('COMPOSER_FILE', dirname(__DIR__) . '/composer.json');

$composer_json = file_get_contents(COMPOSER_FILE);

$composer_data = json_decode(
    $composer_json,
    null,
    // Default
    512,
    JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
);

$composer_data['config']['platform']['composer'] = $version;
ksort($composer_data['config']['platform']);
$composer_data['require-dev']['composer'] = $version;
ksort($composer_data['require-dev']);

file_put_contents(
    COMPOSER_FILE,
    json_encode(
        $composer_data,
        JSON_PRETTY_PRINT,
    ),
);

define('PACKAGE_FILE', dirname(__DIR__) . '/package.json');

$package_json = file_get_contents(PACKAGE_FILE);

$package_data = json_decode(
    $package_json,
    null,
    // Default
    512,
    JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
);

$package_data['engines']['composer'] = $version;
ksort($package_data['engines']);

file_put_contents(
    PACKAGE_FILE,
    json_encode(
        $package_data,
        JSON_PRETTY_PRINT,
    ),
);
